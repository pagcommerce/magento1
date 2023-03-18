<img src="https://avatars.githubusercontent.com/u/128087947?s=400&u=f71900b26de86f2942b40c8c4e9d0d70df39b3d8&v=4&s=200" width="127px" height="127px" align="left"/>

# Pagcommerce Magento 1

Módulo de integração Pagcommerce para Magento 1.x

<br>

## Requisitos

- [Magento Community](https://magento.com/products/community-edition) 1.7.x, 1.8.x ou 1.9.x.
- [PHP](http://php.net) >= 5.4.x
- Cron

## Instalação

### Manual

1. Clique [aqui](https://github.com/pagcommerce/magento1/releases) e baixe o arquivo `.zip` de nossa versão mais recente.
2. Descompacte o arquivo **zip** e faça o upload para o diretório raiz da sua instalação Magento.
3. Limpe o cache em `Sistema > Gerenciamento de Cache`

### Modgit

O modgit é um script SHELL desenvolvido para automatizar a instalação de módulos para Magento 1.x  <br>
Para fazer a instalação, é necessário acessar seu servidor por SSH, entrar do diretório raiz do seu Magento e executar os seguintes comandos:
```
curl https://raw.githubusercontent.com/jreinke/modgit/master/modgit > modgit
```
```
chmod +x modgit
```

```
./modgit init 
```

```
./modgit clone Pagcommerce_Payment https://github.com/pagcommerce/magento1.git
```
Limpe o cache em `Sistema > Gerenciamento de Cache`


## Configuração

1. Acesse o painel administrativo da sua loja
2. Vá em `Sistema > Configuração > Métodos de Pagamento > Pagcommerce - Configurações Gerais`
3. Altere o **Modo** para **Produção** 
4. Informe sua **Api_Key** e sua **Api_Secret** - Caso não tenha as chaves, acesse nosso site e clique no menu **"Configurações - Chaves de API"**
5. Caso o documento (CPF/CNPJ) da sua loja não seja o padrão **tav_vat** do Magento, escolha o atributo correto na lista de opções de  **Atributo CPF/CNPJ do cliente**
6. Salve as configurações
7. Ative o PIX na sessão "Pagcommerce PIX"
8. Ative o Boleto na sessão "Pagcommerce Boleto"

### Configuração de cancelamento automático de boletos não pagos

Pedidos que forem criados na plataforma com boleto como forma de pagamento,
deverão ser cancelados após o vencimento. O módulo possui um processo
automatizado que, identifica os boletos pendentes e, se em **15** dias após a
data de vencimento não houver o pagamento, o pedido é **cancelado**.

Para que este processo funcione é preciso que as a _cron_ da plataforma seja
configurada no servidor:

`*/5 * * * * sh /path/to/your/magento/site/root/cron.sh`

A instrução acima irá executar o módulo de gerenciamento de tarefas agendadas
a cada 5 minutos.

Mais detalhes sobre esta configuração no [link](https://amasty.com/blog/configure-magento-cron-job/)
