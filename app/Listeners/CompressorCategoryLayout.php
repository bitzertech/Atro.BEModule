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

namespace BEModule\Listeners;


use Atro\Core\EventManager\Event;
use Atro\Core\Utils\Util;
use Atro\Listeners\AbstractLayoutListener;

class CompressorCategoryLayout extends AbstractLayoutListener
{
    public function detail(Event $event)
    {
        if ($this->getRelatedEntity($event) == 'Compressor') {
            $result = $event->getArgument('result');

            if (!str_contains(json_encode($result), '"CompressorCategory__mainCategory"')) {
                $result[0]['rows'][] = [['name' => 'CompressorCategory__mainCategory'], false];
            }

            $event->setArgument('result', $result);
        }

    }
}
