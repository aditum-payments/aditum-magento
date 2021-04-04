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
            ->newTable($setup->getTable('aditum_config'))
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'ID'
            )
            ->addColumn(
                'aditum_option',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Option name'
            )
            ->addColumn(
                'aditum_value',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                65535,
                ['nullable' => false, 'default' => ''],
                'Value'
            )
            ->setComment("Aditum Config");
        $setup->getConnection()->createTable($table);

        $sql = "INSERT INTO aditum_config (aditum_option,aditum_value) VALUES ('token_value',' ')";
        $setup->getConnection()->query($sql);
        $sql = "INSERT INTO aditum_config (aditum_option,aditum_value) VALUES ('token_expires','0')";
        $setup->getConnection()->query($sql);

        $table = $setup->getConnection()
            ->newTable($setup->getTable('aditum_order'))
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
                'aditum_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Aditum ID'
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
            ->setComment("Aditum Orders");
        $setup->getConnection()->createTable($table);

        $setup->endSetup();
    }
}
