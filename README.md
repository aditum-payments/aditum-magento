# Módulo de pagamento Aditum do Magento 2 

Este módulo adiciona a forma de pagamento da Aditum Pagamentos ao Magento 2.

## Instalação

composer require aditumpayment/magento2

## Depois de instalar o módulo, execute os seguintes comandos:

php bin/magento setup:upgrade

php bin/magento setup:di:compile

php bin/magento setup:static-content:deploy

php bin/magento cache:flush


## Configuração

Na administração do Magento vá para:

Store -> Configuration -> Sales -> Payment Methods

Faça as configurações nas novas abas criadas com nome Aditum.
