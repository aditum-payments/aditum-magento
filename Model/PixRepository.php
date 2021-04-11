<?php
namespace Aditum\Payment\Model;

class PixRepository //implements \Aditum\Payment\Api\PixRepositoryInterface
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
