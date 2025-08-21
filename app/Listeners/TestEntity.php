<?php

namespace BEModule\Listeners;

use Atro\Listeners\AbstractListener;
use Atro\Core\EventManager\Event;

/**
 * Test Entity Listener
 */
class TestEntity extends AbstractListener
{
  public function beforeSave(Event $event)
    {
        $entity = $event->getArgument('entity');

      // do something here
    }
}
