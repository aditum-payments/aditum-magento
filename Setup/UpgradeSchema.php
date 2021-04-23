<?php

namespace AditumPayment\Magento2\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();
        // 0.0.2
//        if (version_compare($context->getVersion(), '0.0.2', '<')) {
//            $installer->getConnection()->changeColumn(
//                $installer->getTable( 'sales_order' ),
//                'ext_order_id',
//                'ext_order_id',
//                [
//                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
//                    'length' => 255
//                ]
//            );
//        }
        $installer->endSetup();
    }
}
