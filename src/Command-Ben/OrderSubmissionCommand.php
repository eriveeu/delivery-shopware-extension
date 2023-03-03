<?php declare(strict_types=1);

namespace NewMobilityEnterprise\Command;

use GreenToHome\Api\CompanyApi;
use GreenToHome\Configuration;
use GreenToHome\Model\Address;
use GreenToHome\Model\Customer;
use GreenToHome\Model\Parcel;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OrderSubmissionCommand extends Command
{
    // Command name
    protected static $defaultName = 'gth:submit-orders';
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;


    public function __construct(SystemConfigService $systemConfigService, EntityRepositoryInterface $orderRepository)
    {
        parent::__construct();
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
    }

    // Provides a description, printed out in bin/console
    protected function configure(): void
    {
        $this->setDescription('Submits paid orders to GTH');
    }

    // Actual code executed in the command
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $gthEnv = $this->systemConfigService->get('GreenToHome.config.gthEnvironment');
        $apiKey = $this->systemConfigService->get('GreenToHome.config.apikey');

        print_r("$gthEnv $apiKey");

        $config = Configuration::getDefaultConfiguration()->setApiKey('key', $apiKey);
        $apiInstance = new CompanyApi(
            new \GuzzleHttp\Client,
            $config
        );

        $criteria = new Criteria();
        // get only paid orders
        $criteria->addFilter(new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'));
        $criteria->addAssociations($this->getAssociationsForOrder());
        $orders = $this->orderRepository->search($criteria, Context::createDefaultContext());

        foreach ($orders->getElements() as $k => $order) {
            if ($order->getCustomFields() == null || $order->getCustomFields()["custom_greentohome_delivery_parcelId"] == null) {

                // TODO evaluate how we can use field 'trackingCode' which exists OOTB
                //array_values($order->getDeliveries()->getElements())[0]->getTrackingCodes());

                //order has not been successfully submitted to GTH yet
                $gthParcel = $this->submitOrderGth($order, $apiInstance);

                //update order with parcelId and shippingLabelUrl
                $this->orderRepository->update([
                    [
                        'id' => $order->getId(),
                        'customFields' => [
                            'custom_greentohome_delivery_parcelId' => $gthParcel->getParcel()->getId(),
                            'custom_greentohome_delivery_shippingLabel' => $gthParcel->getParcel()->getLabelUrl(),
                            'custom_greentohome_delivery_parcelStatus' => $gthParcel->getParcel()->getStatus(),
                        ],
                    ]
                ], Context::createDefaultContext());

            }
        }

        $output->writeln('Command has successfully exited' . PHP_EOL);

        return self::SUCCESS;
    }

    private function submitOrderGth($order, $apiInstance)
    {
        $orderAddressEntity = array_values($order->getDeliveries()->getElements())[0]->getShippingOrderAddress();

        $parcel = new Parcel(); // \GreenToHome\Model\Parcel | Parcel to submit
        $parcel->setExternalReference($order->getOrderNumber());
        //$parcel->setComment("kommentar");
        $parcel->setWeight(0);
        $parcel->setLenght(0);
        $parcel->setPackagingUnits(sizeof($order->getLineItems()->getElements())); //TODO multiply by qty

        $customer = new Customer();
        $customer->setName($orderAddressEntity->getFirstName() . " " . $orderAddressEntity->getLastName());
        $customer->setEmail($order->getOrderCustomer()->getEmail());
        $customer->setPhone("+43 666 000000");

        $customerAddress = new Address();
        $customerAddress->setCountry($orderAddressEntity->getCountry()->getIso());
        $customerAddress->setCity($orderAddressEntity->getCity());
        $customerAddress->setZip($orderAddressEntity->getZipcode());
        $customerAddress->setStreet($orderAddressEntity->getStreet());
//        $customerAddress->setComment("Kommentar zur Lieferung");

        $customer->setAddress($customerAddress);
        $parcel->setTo($customer);

        try {
            $result = $apiInstance->submitParcel($parcel);
            //print_r($result);

            echo 'GTH-Paketnummer: ', $result->getParcel()->getId(), PHP_EOL;
            echo 'GTH-Paketstatus: ' , $result->getParcel()->getStatus(), PHP_EOL;
            echo 'GTH-Kundenummer: ' , $result->getParcel()->getTo()->getId(), PHP_EOL;
            echo 'GTH-Label URL: ' , $result->getParcel()->getLabelUrl(), PHP_EOL;
            return $result;
        } catch (Exception $e) {
            echo 'Exception when calling CompanyApi->submitParcel: ', $e->getMessage(), PHP_EOL;
        }
    }

    private function getAssociationsForOrder(): array
    {
        return [
            'addresses',
            'lineItems.product',
            'lineItems.product.options',
            'lineItems.product.options.group',
            'currency',
            'orderCustomer.customer',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingOrderAddress.countryState',
            'transactions.stateMachineState.technicalName'
        ];
    }
}
