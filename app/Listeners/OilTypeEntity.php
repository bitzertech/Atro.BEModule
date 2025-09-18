<?php

namespace BEModule\Listeners;

use Atro\Listeners\AbstractListener;
use Atro\Core\EventManager\Event;

/**
 * OilType Entity Listener
 */
class OilTypeEntity extends AbstractListener
{
  public function beforeSave(Event $event)
    {
        $entity = $event->getArgument('entity');

      // do something here
    }
}
