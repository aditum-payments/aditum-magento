<?php
namespace AditumPayment\Magento2\Model;

class PixRepository //implements \AditumPayment\Magento2\Api\PixRepositoryInterface
{
    protected $assetRepo;

    public function __construct(
        \Magento\Framework\View\Asset\Repository $assetRepo
    )
    {
        $this->assetRepo = $assetRepo;
    }
    public function getOption($option)
    {
        $optionReturn = false;
        if($option=="checkoutimagemurl") {
            $optionReturn[$option] = $this->assetRepo->getUrl("Tatix_PIX::images/logo_pix.png");
        }
        return $optionReturn;
    }
}
