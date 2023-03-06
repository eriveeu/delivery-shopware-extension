<?php declare(strict_types=1);

namespace NewMobilityEnterprise\Service;

class GTHToShopware
{
    private String $gthEnv;
    private String $apiKey;
    private String $customParcelIdField;
    private String $customStickerUrlField;
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository,
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;

        $this->gthEnv = $systemConfigService->get('GreenToHome.config.gthEnvironment');
        $this->apiKey = $systemConfigService->get('GreenToHome.config.apikey');
        $this->customParcelIdField = $systemConfigService->get('GreenToHome.config.parcelIdFieldName') ?? 'custom_gth_ParcelID';
        $this->customStickerUrlField = $systemConfigService->get('GreenToHome.config.stickerUrlFieldName') ?? 'custom_gth_StickerUrl';
    }

    private function getGTHParcelById(String $id) {
        // TODO
    }

    private function getShopwareParcels() {
        // TODO
    }

    public function processChangedOrders() {
        $parcels = $this->getShopwareParcels();

        if (count($parcels) === 0) { return; }
        
        foreach($parcels as $parcel) {
            $GthParcelStatus = $this->getGTHParcelById($parcel->getId());
            $SWOrderStatus = $parcel->getOrderState(); // TODO

            if ($GthParcelStatus !== $SWOrderStatus) {
                // Set Shopware order status
            }
        }
    }
}
