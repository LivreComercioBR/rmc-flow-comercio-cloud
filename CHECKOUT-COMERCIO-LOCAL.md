# Checkout do RMC Flow Comércio Local

A edição Local custa R$ 99,00 em pagamento único. A licença permanente é emitida para o computador que gerou o código de solicitação. O checkout usa a integração Checkout Pro já configurada no Comércio Cloud. Os pedidos locais ficam separados das assinaturas.

## Implantação na HostGator

1. Publique os arquivos do aplicativo pela rotina habitual.
2. Na raiz da aplicação Laravel, rode `php artisan migrate --force`.
3. Confirme que `APP_URL` e `MERCADO_PAGO_NOTIFICATION_URL` são HTTPS públicos, e que o Access Token e o segredo do webhook correspondem à mesma aplicação e ao ambiente escolhido. A URL de notificação continua sendo `/mercado-pago/webhook`.
4. Habilite `MERCADO_PAGO_LOCAL_CHECKOUT_ENABLED=true` na configuração privada e rode `php artisan optimize:clear`. Essa opção habilita somente o checkout Local e começa desabilitada.
5. Para conferir o fluxo em sandbox, use um ambiente separado com credenciais de teste e `MERCADO_PAGO_SANDBOX=true`. Se a notificação de teste não chegar, use o simulador de Webhooks do Mercado Pago com o ID do pagamento de teste. Confira o pedido com o comando abaixo. O modo sandbox segue a configuração da integração compartilhada. Evite alterá-lo no Cloud em produção enquanto clientes estiverem usando o checkout.
6. Após a homologação, habilite a compra em produção e aponte os botões da loja e da página de vendas para `https://sistema.comercio.cloud.rmcsites.com.br/edicao-local`.

## Atendimento

1. Peça ao comprador a referência do pedido, disponível na tela de retorno e preenchida no link de suporte.
2. Rode `php artisan comercio-local:pedido REFERENCIA`. Confirme que aparece **Confirmado** e que o e-mail e o pagamento correspondem à compra. Se houver dúvida, consulte o pagamento no painel do Mercado Pago.
3. Entregue o instalador. O comprador instala o programa e envia ao suporte o código de solicitação gerado no computador que vai usar.
4. Emita a licença do produto **RMC Flow Comércio Local** no Painel de Licenças e devolva o código de ativação ao comprador. A emissão e o envio são manuais.

A tela de retorno informa o estado registrado pela notificação do Mercado Pago. Em caso de contestação ou reembolso, o pedido exige análise. Se houver mais de um pagamento aprovado com a mesma referência, confira as cobranças no Mercado Pago antes de emitir ou reembolsar.

