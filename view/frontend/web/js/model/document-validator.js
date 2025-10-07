define(
    ['mage/translate', 'Magento_Ui/js/model/messageList', 'jquery'],
    function ($t, messageList, $) {
        'use strict';

        return {
            validate: function () {
                var paymentMethod = $('input[name="payment[method]"]:checked').val();
                var isValid = true;
                var documentField = null;
                var documentValue = '';

                // Identifica o campo documento baseado no método de pagamento selecionado
                if (paymentMethod === 'aditumcc') {
                    documentField = $('#aditumcc_document');
                } else if (paymentMethod === 'aditumboleto') {
                    documentField = $('#aditumboleto_boletodocument');
                } else if (paymentMethod === 'aditumpix') {
                    documentField = $('#aditumpix_pixdocument');
                } else {
                    return true; // Não é um método Aditum, pula validação
                }

                if (!documentField || !documentField.length) {
                    return true; // Campo não encontrado, pula validação
                }

                documentValue = documentField.val();

                // Verifica se o campo está vazio
                if (!documentValue || documentValue.trim() === '') {
                    var fieldLabel = paymentMethod === 'aditumcc' ? 'CPF' : 'CPF/CNPJ';
                    messageList.addErrorMessage({
                        message: $t('O campo ' + fieldLabel + ' é obrigatório')
                    });
                    return false;
                }

                // Valida CPF/CNPJ
                var verifica = verifica_cpf_cnpj(documentValue);
                if (verifica === false) {
                    messageList.addErrorMessage({
                        message: $t('CPF ou CNPJ inválido')
                    });
                    return false;
                }

                return true;

                function verifica_cpf_cnpj(valor) {
                    valor = valor.toString();
                    valor = valor.replace(/[^0-9]/g, '');
                    if (valor.length === 11) {
                        return valida_cpf(valor) ? 'CPF' : false;
                    } else if (valor.length === 14) {
                        return valida_cnpj(valor) ? 'CNPJ' : false;
                    } else {
                        return false;
                    }
                }

                function calc_digitos_posicoes(digitos, posicoes = 10, soma_digitos = 0) {
                    digitos = digitos.toString();
                    for (var i = 0; i < digitos.length; i++) {
                        soma_digitos = soma_digitos + (digitos[i] * posicoes);
                        posicoes--;
                        if (posicoes < 2) {
                            posicoes = 9;
                        }
                    }
                    soma_digitos = soma_digitos % 11;
                    if (soma_digitos < 2) {
                        soma_digitos = 0;
                    } else {
                        soma_digitos = 11 - soma_digitos;
                    }
                    var cpf = digitos + soma_digitos;
                    return cpf;
                }

                function valida_cpf(valor) {
                    valor = valor.toString();
                    valor = valor.replace(/[^0-9]/g, '');
                    var digitos = valor.substr(0, 9);
                    var novo_cpf = calc_digitos_posicoes(digitos);
                    novo_cpf = calc_digitos_posicoes(novo_cpf, 11);
                    return novo_cpf === valor;
                }

                function valida_cnpj(valor) {
                    valor = valor.toString();
                    valor = valor.replace(/[^0-9]/g, '');
                    var cnpj_original = valor;
                    var primeiros_numeros_cnpj = valor.substr(0, 12);
                    var primeiro_calculo = calc_digitos_posicoes(primeiros_numeros_cnpj, 5);
                    var segundo_calculo = calc_digitos_posicoes(primeiro_calculo, 6);
                    var cnpj = segundo_calculo;
                    return cnpj === cnpj_original;
                }
            }
        };
    }
);