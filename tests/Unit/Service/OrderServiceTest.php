<?php

declare(strict_types=1);

namespace EriveTests\Delivery\Unit\Service;

use Erive\Delivery\Configuration;
use Erive\Delivery\Service\OrderService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderServiceTest extends TestCase
{
    protected SystemConfigService $systemCofigServiceMock;
    protected EntityRepository $orderRepositoryMock;
    protected EntityRepository $orderDeliveryRepositoryMock;
    protected LoggerInterface $loggerMock;
    protected OrderService $orderService;

    public function setUp(): void
    {
        parent::setUp();

        $this->systemCofigServiceMock = $this->createMock(SystemConfigService::class);
        $this->orderRepositoryMock = $this->createMock(EntityRepository::class);
        $this->orderDeliveryRepositoryMock = $this->createMock(EntityRepository::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->orderService = new OrderService(
            $this->systemCofigServiceMock,
            $this->orderRepositoryMock,
            $this->orderDeliveryRepositoryMock,
            $this->loggerMock
        );
    }

    /** @test */
    public function orders_get_processed(): void
    {
        $returnOrderList = new class () extends EntitySearchResult {
            protected array $elements = ['', '', ''];

            /** @return \Generator<TElement> */
            public function getIterator(): \Generator
            {
                yield from $this->elements;
            }
        };

        $this->orderRepositoryMock->expects($this->once())->method('search')->willReturn($returnOrderList);
        $this->orderService->expects($this->exactly(3))->method('processOrder');

        $result = $this->orderService->processAllOrders();

        $this->assertEmpty($result);
    }

}
