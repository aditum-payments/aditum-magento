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

## Contato

Para suporte técnico como dúvidas na instalação e/ou problemas nos contate no email: suporte@gum.net.br

Página oficial do desenvolvedor: https://gum.net.br/

## Informações de licença

@author Gustavo Ulyssea - gustavo.ulyssea@gmail.com

@copyright Copyright (c) 2021-2021 Aditum Pagamentos

@package Aditum Payment Magento 2

All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:

1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY GUM Net (https://gum.net.br). AND CONTRIBUTORS
``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE FOUNDATION OR CONTRIBUTORS
BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
