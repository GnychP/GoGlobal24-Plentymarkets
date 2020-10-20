<?php
namespace GoGlobal24\EventProcedures;

use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Shipping\Returns\Services\RegisterReturnsService;

use Plenty\Plugin\Log\Loggable;

use GoGlobal24\Helpers\Constants;

class Procedures
{

    use Loggable;
    /**
     * @param \Plenty\Modules\EventProcedures\Events\EventProceduresTriggered $event
     * @param \Plenty\Modules\Order\Shipping\Returns\Services\RegisterReturnsService $registerReturnsService
     * @return void
     */
    public function registerGo(EventProceduresTriggered $event, RegisterReturnsService $registerReturnsService)
    {
      $this->getLogger('GoGlobal24')->info('GoGlobal24::procedures.registerGo', [
              'Test' => 'test process'
            ]);
        $order = $event->getOrder();
        $registerReturnsService->registerReturns(Constants::PLUGIN_NAME, [$order->id]);
    }
}
