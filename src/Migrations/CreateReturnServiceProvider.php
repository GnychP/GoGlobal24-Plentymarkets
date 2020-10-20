<?php
namespace GoGlobal24\Migrations;

use Plenty\Plugin\Log\Loggable;
use Plenty\Modules\Order\Shipping\Returns\Contracts\ReturnsServiceProviderRepositoryContract;

use GoGlobal24\Helpers\Constants;

class CreateReturnServiceProvider
{
    use Loggable;

    private $returnsServiceProviderRepository;

    public function __construct(ReturnsServiceProviderRepositoryContract $returnsServiceProviderRepository)
    {
        $this->returnsServiceProviderRepository = $returnsServiceProviderRepository;
    }

    public function run()
    {
        try {
          $this->getLogger(Constants::PLUGIN_NAME)
              ->info(
                  "Trying to save migration ",
                  ['test' => 'test']
              );
            $this->returnsServiceProviderRepository->saveReturnsServiceProvider(Constants::PLUGIN_NAME);
        } catch (\Exception $exception) {
            $this->getLogger(Constants::PLUGIN_NAME)
                ->critical(
                    "Could not migrate/create new shipping provider: " . $exception->getMessage(),
                    ['error' => $exception->getTrace()]
                );
        }
    }
}
