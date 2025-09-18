<?php

namespace BEModule\Listeners;

use Atro\Listeners\AbstractListener;
use Atro\Core\EventManager\Event;

/**
 * Refrigerant Entity Listener
 */
class RefrigerantEntity extends AbstractListener
{
  public function beforeSave(Event $event)
    {
        $entity = $event->getArgument('entity');

      // do something here
    }
}
