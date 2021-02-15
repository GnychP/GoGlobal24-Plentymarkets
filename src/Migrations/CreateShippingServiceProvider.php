<?php

namespace GoGlobal24\Migrations;

use Exception;
use Plenty\Modules\Order\Shipping\ServiceProvider\Contracts\ShippingServiceProviderRepositoryContract;
use Plenty\Plugin\Log\Loggable;
use GoGlobal24\Helpers\Constants;

class CreateShippingServiceProvider
{
    use Loggable;

    private $shippingServiceProviderRepository;

    public function __construct(ShippingServiceProviderRepositoryContract $shippingServiceProviderRepository)
    {
        $this->shippingServiceProviderRepository = $shippingServiceProviderRepository;
    }

    public function run()
    {
        try {
            $this->shippingServiceProviderRepository->saveShippingServiceProvider(Constants::PLUGIN_NAME, 'GoGlobal24');
        } catch (Exception $exception) {
          $this->getLogger(Constants::PLUGIN_NAME)
              ->critical(
                  "Could not create new shipping provider: " . $exception->getMessage(),
                  ['error' => $exception->getTrace()]
              );
        }
    }
}
