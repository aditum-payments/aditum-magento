# Relatório de Migração de Logs para logApi

## Resumo
Todos os logs do sistema no arquivo `Helper/Api.php` foram migrados para usar o método `logApi`, que direciona os logs para o arquivo específico da API (`/var/log/aditum_api.log`).

## Logs Migrados

### Método extCreateOrderBoleto
- ✅ `ADITUM BOLETO create order started`
- ✅ `BOLETO Order ID`
- ✅ `BOLETO API URL`
- ✅ `BOLETO Client ID`
- ✅ `BOLETO Payment Additional Info`
- ✅ `BOLETO Street Array`
- ✅ `BOLETO Address Config`
- ✅ `BOLETO Grand Total (cents)`
- ✅ `BOLETO sending request to API...`
- ✅ `External Apitum API Return`

### Método extCreateOrderCc
- ✅ `ADITUM CC create order started`
- ✅ `CC Order ID`
- ✅ `CC API URL`
- ✅ `CC Client ID`
- ✅ `CC Payment Additional Info`
- ✅ `CC PreAuth`
- ✅ `Card CCDC Type`
- ✅ `CC Street Array`
- ✅ `CC Address Config`
- ✅ `CC sending request to API...`
- ✅ `CC API Response`

### Método createOrderPix
- ✅ `ADITUM PIX create order started`
- ✅ `PIX Order ID`
- ✅ `PIX API URL`
- ✅ `PIX Client ID`
- ✅ `PIX Payment Additional Info`
- ✅ `PIX Grand Total (cents)`
- ✅ `PIX sending request to API...`
- ✅ `PIX API Response`

### Método logError
- ✅ `Aditum Request error` (migrado de error para logApi)

### Métodos de processamento de itens
- ✅ Logs de debug no `getGeneralNormalizedItems`

## Total de Logs Migrados
- **34 logs** convertidos de `$this->logger->` para `$this->logApi()`
- **1 log** do sistema mantido (fallback no método logApi)

## Benefícios da Migração
1. **Separação de logs**: Logs da API agora ficam em arquivo separado (`/var/log/aditum_api.log`)
2. **Melhor organização**: Facilita o debug e monitoramento específico da API
3. **Fallback seguro**: Se o logger da API falhar, usa o logger do sistema
4. **Níveis de log**: Suporte a diferentes níveis (info, error, warning, debug)

## Estrutura Final
- Logs da API: `/var/log/aditum_api.log`
- Logs do sistema: `/var/log/system.log` (apenas fallback)
- Método logApi com suporte a níveis e contexto