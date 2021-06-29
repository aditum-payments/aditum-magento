
# Instalação o módulo magento Aditum


### Baixar módulo
[link do packgist](https://packagist.org/packages/aditum/magento2-payment)

Na raiz do projeto executar o comando:

```shell
composer require aditum/magento2-payment
```

### Iniciar a configuração do módulo na loja
```shell
bin/magento setup:upgrade
```


### Compilar o projeto/loja novamente
```shell
bin/magento setup:di:compile
```


### Configurar o pagamento
Na tela administrativa do magento, seguir o caminho:
```
Stores > configuration > Sales > Payment Methods > others
```

### Aditum Pagamentos
- **Ativar** `Yes/No` 
*Ativa ou desativa módulo de pagamento Aditum*

- **Ambiente** `Produção/Homologação`
*Seleciona entre fazer transação em produção ou em ambiente de desenvolvimento(Homologação)*

- **Tipo de anti-fraude** `ClearSale/Konduto`
*Seleciona qual anti-fraude vai ser usado na tela de checkout*

- **ID do anti-fraude** `id`
*ID correspondente ao anti-fraude selecionado*

- **CNPJ** `número do cnpj`
*Deve ser colocado o cnpj cadastrado na Aditum*

- **Merchant Token** `id do merchant`
*ID do merchant da Aditum*

- **Tempo de expiração do pedido** `números de dias`
*Quanto tempo depois do pedido criado sem uma confirmação do pagamento ele vai expirar*

- **Status do pedido criado** `Pending/Processing/suspected fraud/Complete/Closed/Canceled`
*Status inicial do pedido criado antes de receber uma confirmação de pagamento*

- **Definições do Endereço - Rua** `Line 0`
- **Definições do Endereço - Número** `Line 1`
- **Definições do Endereço - Complemente** `Line 2`
- **Definições do Endereço - Bairro** `Line 3`


### Aditum cartão de crédito

- **Ativar cartão de crédito** `Yes/No` 
*Ativa ou desativa opção de crédito*

- **Máximo de parcelas** `mínimo de 1 e máximo de 20`

###  Aditum Boleto
- **Ativar  boleto** `Yes/No` 
*Ativa ou desativa opção de boleto*

- **Dias para vencimento do boleto** `número de dias` 
*Quantos dias para efetuar o pagamento do boleto*

- **Dias para multa** `número de dias` 
*Quantos dia para começar a vale a multa no boleto*

- **Valor fixo da multa**  `valor a ser pago na multa`

- **Valor percentual da multa**  

### Registrar webhook
https://domínio/aditum/apicallback
