<?php
namespace GoGlobal24\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\Order\Shipping\Returns\Services\ReturnsServiceProviderService;

use GoGlobal24\Helpers\Constants;
use GoGlobal24\EventProcedures\Procedures;
use GoGlobal24\Controllers\ShippingController;

class ProcedurePluginServiceProvider extends ServiceProvider
{
    /**
     * @param EventProceduresService $eventProceduresService
     * @param ReturnsServiceProviderService $returnsServiceProviderService
     * @param ShippingServiceProviderService $shippingServiceProviderService
     * @return void
     */
    public function boot(
      EventProceduresService $eventProceduresService,
      ShippingServiceProviderService $shippingServiceProviderService,
      ReturnsServiceProviderService $returnsServiceProviderService
    )
    {
      //WARNING: DONT CHANGE NAMES! IF NAME CHANGE USER MUST CONFIGURE PLUGIN FROM SCRATCH

      $returnsServiceProviderService->registerReturnsProvider(
          Constants::PLUGIN_NAME,
          'GoGlobal24',
          ShippingController::class
      );

      $shippingServiceProviderService->registerShippingProvider(
          Constants::PLUGIN_NAME,
            ['de' => 'GoGlobal24', 'en' => 'GoGlobal24'],
          [
              'GoGlobal24\\Controllers\\ShippingController@registerShipments',
              // 'GoGlobal24\\Controllers\\ShippingController@deleteShipments',
              // 'GoGlobal24\\Controllers\\ShippingController@getLabels',
          ]
      );

      $eventProceduresService->registerProcedure(
          'GoReturn',
          ProcedureEntry::EVENT_TYPE_ORDER,
          ['de' => 'GoGlobal24 Return', 'en' => 'GoGlobal24 Return'],
          Procedures::class .  '@registerGo'
      );
    }
}
