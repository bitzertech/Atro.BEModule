<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace BEModule\Repositories;

use Atro\Core\Templates\Repositories\Hierarchy;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class CompressorCategory extends Hierarchy
{
    public function getCategoryRoute(Entity $entity, bool $isName = false): string
    {
        // prepare result
        $result = '';

        // prepare data
        $data = [];
        $parents = $this->getParents($entity);

        while (!empty($parents[0])) {
            // push id
            $parent = $parents->offsetGet(0);
            if (!$isName || empty($parent->get('name'))) {
                $data[] = $parent->get('id');
            } else {
                $data[] = trim((string)$parent->get('name'));
            }

            // to next compressorCategory
            $parents = $this->getParents($parent);
        }

        if (!empty($data)) {
            if (!$isName) {
                $result = '|' . implode('|', array_reverse($data)) . '|';
            } else {
                $result = implode(' / ', array_reverse($data));
            }
        }

        return $result;
    }

    protected function getParents(Entity $entity): ?EntityCollection
    {
        if(!empty($entity->get('parentsIds'))) {
            $parents =  $this->where(['id' => $entity->get('parentsIds')])->find();
            $entity->set('parents', $parents);
            return $parents;
        }

        return $entity->get('parents');
    }

    /**
     * @param Entity $entity
     * @param array $options
     *
     * @throws BadRequest
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if ($entity->isAttributeChanged('parentsIds') && !empty($entity->isAttributeChanged('parentsIds'))) {
            $parents = $this->where(['id'=> $entity->get('parentsIds')])->find();
            if (!empty($parents) && count($parents) > 0) {
                if (!$this->getConfig()->get('compressorCanLinkedWithNonLeafCategories', false)) {
                    foreach ($parents as $parent) {
                        $categoryParentCompressors = $parent->get('compressors');
                        if (!empty($categoryParentCompressors) && count($categoryParentCompressors) > 0) {
                            throw new BadRequest($this->exception('parentCategoryHasCompressors'));
                        }
                    }
                }
            }
        }

        if ($entity->isNew()) {
            $entity->set('sortOrder', time());
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        // activate parents
        $this->activateParents($entity);

        // deactivate children
        $this->deactivateChildren($entity);

        parent::afterSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        if ($this->getConfig()->get('behaviorOnCategoryDelete', 'cascade') !== 'cascade') {
            if (!empty($compressors = $entity->get('compressors')) && count($compressors) > 0) {
                throw new BadRequest($this->exception("categoryHasCompressors"));
            }

            if (!empty($compressorCategory = $entity->get('compressorCategories')) && count($compressorCategories) > 0) {
                throw new BadRequest($this->exception("categoryHasChildCategoryAndCantBeDeleted"));
            }
        }

    }

    public function remove(Entity $entity, array $options = [])
    {
        $result = parent::remove($entity);

        $this->getEntityManager()->getRepository('CompressorCategory')
            ->where(["categoryId"  => $entity->get('id')])
            ->removeCollection();

        return $result;
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        $this->getConnection()->createQueryBuilder()
            ->update('compressor_category')
            ->set('deleted',':deleted')
            ->where('compressorCategory_id = :categoryId')
            ->setParameter('categoryId', $entity->get('id'))
            ->setParameter('deleted',false, ParameterType::BOOLEAN)
            ->executeQuery();

    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'CompressorCategory');
    }

    protected function translate(string $key, string $label = 'labels', string $scope = 'Global'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    protected function getCompressorRepository(): Compressor
    {
        return $this->getEntityManager()->getRepository('Compressor');
    }

    protected function activateParents(Entity $entity): void
    {
        // is activate action
        $isActivate = $entity->isAttributeChanged('isActive') && $entity->get('isActive');

        if (empty($entity->recursiveSave) && $isActivate && !$entity->isNew()) {
            // update all parents
            $ids = $this->getParentsRecursivelyArray($entity->get('id'));
            foreach ($ids as $id) {
                $parent = $this->get($id);
                if (!empty($parent)) {
                    $parent->set('isActive', true);
                    $this->saveEntity($parent);
                }
            }
        }
    }

    protected function deactivateChildren(Entity $entity): void
    {
        // is deactivate action
        $isDeactivate = $entity->isAttributeChanged('isActive') && !$entity->get('isActive');

        if (empty($entity->recursiveSave) && $isDeactivate && !$entity->isNew()) {
            // update all children
            $ids = $this->getChildrenRecursivelyArray($entity->get('id'));
            foreach ($ids as $id) {
                $child = $this->get($id);
                $child->set('isActive', false);
                $this->saveEntity($child);
            }
        }
    }

    protected function saveEntity(Entity $entity): void
    {
        // set flag
        $entity->recursiveSave = true;

        $this->getEntityManager()->saveEntity($entity);
    }

    public function updateRoute(Entity $entity): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('compressorCategory'), 'c')
            ->set('category_route', ':categoryRoute')
            ->set('category_route_name', ':categoryRouteName')
            ->where('c.id = :id')
            ->setParameter('categoryRoute', $this->getCategoryRoute($entity))
            ->setParameter('categoryRouteName', $this->getCategoryRoute($entity, true))
            ->setParameter('id', $entity->get('id'))
            ->executeQuery();
    }

}
