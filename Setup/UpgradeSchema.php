<?php

namespace Aditum\Payment\Setup;

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
        if (version_compare($context->getVersion(), '0.0.2', '<')) {
            if ($installer->tableExists('ame_order')) {
                $installer->getConnection()->dropTable($installer->getTable('ame_order'));
            }
            $installer->getConnection()->changeColumn(
                $installer->getTable( 'sales_order' ),
                'ext_order_id',
                'ext_order_id',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255
                ]
            );
        }
        // 0.0.3
        if (version_compare($context->getVersion(), '0.0.3', '<')) {
            $installer->getConnection()
                ->addColumn(
                    $installer->getTable('quote'),
                    'pix_security_key',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'length' => 255,
                        'comment' => 'PIX Security Key'
                    ]
                );
            //Order table
            $installer->getConnection()
                ->addColumn(
                    $installer->getTable('sales_order'),
                    'pix_security_key',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'length' => 255,
                        'comment' => 'PIX Security Key'
                    ]
                );
        }
        $installer->endSetup();
    }
}
