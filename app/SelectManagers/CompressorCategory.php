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

namespace BEModule\SelectManagers;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\IEntity;
use Pim\Core\SelectManagers\AbstractSelectManager;

class CompressorCategory extends AbstractSelectManager
{
    public function addChildrenCount(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper)
    {
        if (!empty($params['aggregation'])) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        $queryConverter = $mapper->getQueryConverter();

        $tableAlias = $queryConverter->getMainTableAlias();
        $fieldAlias = $queryConverter->fieldToAlias('childrenCount');

        $qb->add(
            'select',
            ["(SELECT COUNT(c1.id) FROM {$connection->quoteIdentifier('category_hierarchy')}  AS c1 WHERE c1.parent_id={$tableAlias}.id AND c1.deleted=:false) as $fieldAlias"], true
        );
        $qb->setParameter('false', false, Mapper::getParameterType(false));
    }

    public function applyAdditional(array &$result, array $params)
    {
        parent::applyAdditional($result, $params);

        $result['callbacks'][] = [$this, 'addChildrenCount'];
    }

    protected function boolFilterNotParents(&$result): void
    {
        $notParents = (string)$this->getSelectCondition('notParents');
        if (empty($notParents)) {
            return;
        }

        $compressorCategory = $this->getEntityManager()->getRepository('CompressorCategory')->get($notParents);
        if (!empty($compressorCategory)) {
            $result['whereClause'][] = [
                'id!=' => array_merge($compressorCategory->getParentsIds(), [$compressorCategory->get('id')])
            ];
        }
    }

    protected function boolFilterNotChildren(&$result): void
    {
        $notChildren = (string)$this->getSelectCondition('notChildren');
        if (empty($notChildren)) {
            return;
        }

        $repository = $this->getEntityManager()->getRepository('CompressorCategory');
        $compressorCategory = $repository->get($notChildren);

        if (!empty($compressorCategory)) {
            $result['whereClause'][] = [
                'id!=' => array_merge($repository->getChildrenRecursivelyArray($compressorCategory->get('id')), [$compressorCategory->get('id')])
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterOnlyRootCategory(array &$result)
    {
        if ($this->hasBoolFilter('onlyRootCategory')) {
            $connection = $this->getEntityManager()->getConnection();

            $childrenIds = $connection->createQueryBuilder()
                ->select('distinct(entity_id)')
                ->from('category_hierarchy')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchFirstColumn();

            $result['whereClause'][] = [
                'id!=' => $childrenIds
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterOnlyLeafCategories(array &$result)
    {
        if (!$this->getConfig()->get('compressorCanLinkedWithNonLeafCategories', false)) {

            $connection = $this->getEntityManager()->getConnection();

            $parentIds = $connection->createQueryBuilder()
                ->select('distinct(parent_id)')
                ->from('category_hierarchy')
                ->where('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchFirstColumn();

            if (!empty($parents)) {
                $result['whereClause'][] = [
                    'id!=' => $parentIds
                ];
            }
        }
    }

    /**
     * @param $result
     *
     * @return void
     */
    protected function boolFilterLinkedWithCompressor(&$result)
    {
        if ($this->hasBoolFilter('linkedWithCompressor')) {
            $list = $this
                ->getEntityManager()
                ->getRepository('CompressorCategory')
                ->select(['id', 'categoryRoute'])
                ->join('compressors')
                ->find()
                ->toArray();

            if ($list) {
                $ids = [];

                foreach ($list as $compressorCategory) {
                    $ids[] = $compressorCategory['id'];
                    if(empty($compressorCategory['categoryRoute'])){
                        continue;
                    }
                    $parentCategoriesIds = explode("|", trim($compressorCategory['categoryRoute'], "|"));
                    $ids = array_merge($ids, $parentCategoriesIds);
                }

                $result['whereClause']['id'] = array_unique($ids);
            } else {
                $result['whereClause']['id'] = null;
            }
        }
    }
}
