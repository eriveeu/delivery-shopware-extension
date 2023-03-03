<?php 
declare(strict_types=1);

namespace NewMobilityEnterprise\Command;

use GuzzleHttp\Client;
use GreenToHome\Configuration;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use GreenToHome\Api\CompanyApi;
use GreenToHome\Model\Parcel;
use GreenToHome\Model\Customer;
use GreenToHome\Model\Address;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderSubmissionCommand extends Command
{
    private SystemConfigService $systemConfigService;
    private String $gthEnv;
    private String $apiKey;
    private String $customParcelIdField;
    private String $customStickerUrlField;
    private EntityRepositoryInterface $orderRepository;
    private EntityRepositoryInterface $orderCustomerRepository;
    private EntityRepositoryInterface $orderLineItemRepository;
    private EntityRepositoryInterface $productRepository;
    private EntityRepositoryInterface $orderAddressRepository;
    private EntityRepositoryInterface $countryRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderCustomerRepository,
        EntityRepositoryInterface $orderLineItemRepository,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $orderAddressRepository,
        EntityRepositoryInterface $countryRepository,
    ) {
        parent::__construct();

        $this->systemConfigService = $systemConfigService;
        $this->gthEnv = $this->systemConfigService->get('GreenToHome.config.gthEnvironment');
        $this->apiKey = $this->systemConfigService->get('GreenToHome.config.apikey');
        $this->customParcelIdField = $this->systemConfigService->get('GreenToHome.config.parcelIdFieldName') ?? 'custom_gth_ParcelID';
        $this->customStickerUrlField = $this->systemConfigService->get('GreenToHome.config.stickerUrlFieldName') ?? 'custom_gth_StickerUrl';

        $this->orderRepository = $orderRepository;
        $this->orderCustomerRepository = $orderCustomerRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->productRepository = $productRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->countryRepository = $countryRepository;
    }

    // Provides a description, printed out in bin/console
    protected function configure(): void
    {
        $this->setName('gth:submit-orders')->setDescription('Syncronizes orders from Shopware to GTH System.');
    }

    // Actual code executed in the command
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->processAllOrders();
        $output->writeln(PHP_EOL . 'Execution completed with no fatal error');
        return 0;
    }

    private function getUnsubmittedOrders(): EntitySearchResult
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();

        // TODO - Format Criteria in JSON
        /*
        
        {
            "associations": {
                "order": {
                    "filter": [
                        { "type": "equals", "field": "customeFiels." . $this->customParcelIdField, "value": null }
                    ]
                    "deliveries": {
                        "shippingOrderAddress": { 
                            "country": { }
                        }
                    },
                    "lineItems": {
                        "product": { }
                    },
                    "orderCustomer": { },
                    "transactions": {
                        "filter": [
                            { "type": "equals", "field": "stateMachineState.technicalName", "value": "paid" }
                        ]
                    }
                }
            },
            "includes": {
                "order": [
                    "orderNumber",
                    "customerComment",
                ],
                "orderCustomer": [
                    "firstName",
                    "lastName",
                    "email"
                ],
                "shippingOrderAddress": [
                    "city",
                    "zipCode",
                    "street",
                    "additionalAddressLine1",
                    "additionalAddressLine2",
                    "phoneNumber"
                ],
                "country": [
                    "iso"
                ],
                "lineItems": [
                    "quantity"
                ],
                "product": [
                    "weight",
                    "width",
                    "height",
                    "length"
                ]
            }
        }

        */
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter( 'customFields.' . $this->customParcelIdField, null ),
                new EqualsFilter( 'transactions.stateMachineState.technicalName', 'paid'),
            ])
        );
        $criteria->addAssociations([
            'lineItems.product',
            'orderCustomer.customer',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingOrderAddress.country',
            'transactions.stateMachineState.technicalName'
        ]);

        $results = null;
        try {
            $results = $this->orderRepository->search($criteria, $context);
        } catch (Exception $e) {
            dump ('Exception when searching for unsyncronized orders: ');
            dump (e->getMessage());
        }

        return $results;
    }

    private function processAllOrders(): int
    {
        // print_r("$this->gthEnv $this->apiKey" . PHP_EOL);

        $context = Context::createDefaultContext();
        $errors = [];
        $config = Configuration::getDefaultConfiguration()->setApiKey('key', $this->apiKey);
        $orders = $this->getUnsubmittedOrders();

        foreach ($orders as $order) {
            $shippingAddress = $order->getDeliveries()->first()->getShippingOrderAddress();
            
            $totalWeight = 0;
            $totalVolume = 0;
            $totalPackagingUnits = 0;

            foreach($order->getLineItems()->getElements() as $item) {
                $quantity = intval($item->getQuantity());
                $prodWeight = floatval($item->getProduct()->getWeight());
                $prodWidth = intval($item->getProduct()->getWidth());
                $prodLength = intval($item->getProduct()->getLength());
                $prodHeight = intval($item->getProduct()->getHeight());

                $totalPackagingUnits += $quantity;
                $totalWeight += ($quantity * $prodWeight);
                $totalVolume += (floatval($prodWidth / 1000) * floatval($prodHeight / 1000) * floatval($prodLength / 1000)) * $quantity;
            }

            $apiInstance = new CompanyApi(new \GuzzleHttp\Client, $config);

            $parcelWidth = '0';
            $parcelLenght = '0';
            $parcelHeight = '0';

            // Configuring Parcel
            $parcel = new Parcel(); // \GreenToHome\Model\Parcel | Parcel to submit
            $parcel->setExternalReference($order->getOrderNumber());
            $parcel->setComment($order->getCustomerComment() ?? 'Package volume: ' . $totalVolume . 'm^3');
            $parcel->setWeight($totalWeight);
            $parcel->setWidth($parcelWidth); // ######### - How to calculate for multiple items? - #########
            $parcel->setLenght($parcelLenght); // ######### - How to calculate for multiple items? - #########
            $parcel->setHeight($parcelHeight); // ######### - How to calculate for multiple items? - #########
            $parcel->setPackagingUnits($totalPackagingUnits);

            $customer = new Customer(); // \GreenToHome\Model\Customer
            $customer->setName($order->getOrderCustomer()->getFirstName() . ' ' . $order->getOrderCustomer()->getLastName());
            $customer->setEmail($order->getOrderCustomer()->getEmail());
            $customer->setPhone($shippingAddress->getPhoneNumber() ?? '+43555000000');

            $customerAddress = new Address(); // \GreenToHome\Model\Address
            $customerAddress->setCountry($shippingAddress->getCountry()->getIso());
            $customerAddress->setCity($shippingAddress->getCity());
            $customerAddress->setZip($shippingAddress->getZipcode());
            $customerAddress->setStreet($shippingAddress->getStreet());
            $customerAddress->setStreetNumber('0');
            $a1 = $shippingAddress->getAdditionalAddressLine1();
            $a2 = $shippingAddress->getAdditionalAddressLine2();
            $customerAddress->setComment($a1 . ($a1 && $a2 ? ', ' : '') . $a2);

            $customer->setAddress($customerAddress);
            $parcel->setTo($customer);

            // Sending configured Parcel to GTH Backend
            try {
                $result = $apiInstance->submitParcel($parcel);
                $gthParcelId = $result->getParcel()->getId();
                $gthStickerUrl = $result->getParcel()->getLabelUrl();

                $customFields = $order->getCustomFields();
                $customFields[$this->customParcelIdField] = $gthParcelId;
                $customFields[$this->customStickerUrlField] = $gthStickerUrl;
                $this->orderRepository->update([['id' => $order->getId(), 'customFields'=>$customFields]], $context);

                print_r('Order #' . $order->getOrderNumber() . ' -> GTH-Paketnummer: ' . $gthParcelId . PHP_EOL);
            } catch (Exception $e) {
                $errors[]= 'Exception when processing order number :' . $order->orderNumber . ' : ' . $e->getMessage() . PHP_EOL;
            }
        }

        if (count($errors) > 0) 
        {
            foreach($errors as $error) {
                print_r($error . PHP_EOL);
            }
            // Exit code 1 for error
            return 1;
        }

        if (count($orders) === 0) {
            print_r('No new unhandled orders found');
        }
        // Exit code 0 for success
        return 0;
    }
}
