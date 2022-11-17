<?php

namespace AditumPayment\Magento2\Model;

class AditumApi implements \AditumPayment\Magento2\Api\AditumApiInterface
{
    protected $api;

    protected $brand;

    protected $orderRepository;

    public function __construct(
        \AditumPayment\Magento2\Helper\Api $api,
        \AditumPayment\Magento2\Api\Data\BrandInterface $brand,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->api = $api;
        $this->brand = $brand;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritDoc
     */
    public function getCcBrand(string $ccNumber)
    {
        if (strlen($ccNumber<6)) {
            $this->brand->setBrand("");
            return $this->brand;
        }
        $brand = $this->api->getCcBrand($ccNumber);
        $this->brand->setBrand($brand);
        return $this->brand;
    }

    /**
     * @param int $orderId
     * @return bool
     */
    public function hasInvoices(int $orderId): bool
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($orderId);
        } catch (\Magento\Framework\Exception\InputException $e) {
            return false;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return false;
        }
        return $order->hasInvoices();
    }
}
