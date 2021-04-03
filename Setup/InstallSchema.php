<?php

namespace Aditum\Payment\Setup;

class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{
    public function install(
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\Framework\Setup\ModuleContextInterface $context
    ) {
        $setup->startSetup();

        $table = $setup->getConnection()
            ->newTable($setup->getTable('pix_config'))
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'ID'
            )
            ->addColumn(
                'pix_option',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Option name'
            )
            ->addColumn(
                'pix_value',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Value'
            )
            ->setComment("PIX Config");
        $setup->getConnection()->createTable($table);

        $sql = "INSERT INTO pix_config (pix_option,pix_value) VALUES ('token_value',' ')";
        $setup->getConnection()->query($sql);
        $sql = "INSERT INTO pix_config (pix_option,pix_value) VALUES ('token_expires','0')";
        $setup->getConnection()->query($sql);

        $table = $setup->getConnection()
            ->newTable($setup->getTable('ame_order'))
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'ID'
            )
            ->addColumn(
                'increment_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => false, 'unsigned' => true, 'nullable' => false, 'primary' => false],
                'Increment ID'
            )
            ->addColumn(
                'pix_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'PIX ID'
            )
            ->addColumn(
                'amount',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => false, 'unsigned' => true, 'nullable' => false, 'primary' => false],
                'Amount'
            )
            ->addColumn(
                'status',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Status'
            )
            ->addColumn(
                'qr_code_link',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'QR Code Link'
            )
            ->addColumn(
                'deep_link',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'iOS Andoid Deep Link'
            )
            ->setComment("PIX Orders");
        $setup->getConnection()->createTable($table);

        $setup->endSetup();
    }
}
