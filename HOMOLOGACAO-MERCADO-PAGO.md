# Homologação do Mercado Pago — RMC Flow Comércio Cloud

Este roteiro é para homologação em sandbox, com banco de desenvolvimento separado do banco da HostGator. Não habilite checkout ou ativação em produção durante estes passos.

## 1. Preparar ambiente de teste

- Criar/selecionar uma aplicação própria do RMC Flow Comércio Cloud em Mercado Pago Developers.
- Usar as credenciais de teste do vendedor e criar uma conta de teste comprador no Brasil. Não usar a conta pessoal como comprador de teste.
- Guardar o Access Token e o segredo do webhook em configuração privada do ambiente. Nunca colocar credenciais em Blade, JavaScript, Git ou mensagens.
- Criar e migrar um banco de desenvolvimento isolado. Não apontar o `.env` local para `rona7916_rmc_flow_comercio_cloud` da hospedagem.
- Antes de executar migrações em homologação, conferir a ordem `000000`-`000019`, validar a conexão/versão MySQL 5.7 e revisar o SQL com `php artisan migrate --pretend`. Strings implícitas ficam limitadas a 191 caracteres e os índices compostos evitam exceder o limite antigo de 767 bytes do MySQL 5.7 com `utf8mb4`; a referência de compra usa 160 caracteres.
- Fazer backup do banco de homologação antes da primeira aplicação. Só depois de validar todas as telas e pagamentos no sandbox, planejar backup e janela de manutenção para qualquer migração na hospedagem; não executar migração diretamente em produção durante a homologação.
- Configurar no modo de teste do Mercado Pago o webhook HTTPS público do Comércio Cloud: `/mercado-pago/webhook`, tópico de pagamentos, e copiar o segredo gerado para `MERCADO_PAGO_WEBHOOK_SECRET`.
- Preencher `MERCADO_PAGO_ACCESS_TOKEN`, `MERCADO_PAGO_NOTIFICATION_URL`, `MERCADO_PAGO_WEBHOOK_SECRET` e `APP_URL` com URLs HTTPS do ambiente de homologação.
- Manter `MERCADO_PAGO_SANDBOX=true`. Habilitar `MERCADO_PAGO_CHECKOUT_ENABLED=true` somente quando banco, URL e credenciais de teste estiverem conferidos. A ativação pode começar em `false` para validar o estado de pagamento aguardando política.
- No terminal do ambiente configurado, execute `php artisan mercado-pago:preflight`. O comando verifica sandbox, checkout, URLs HTTPS e presença das credenciais sem mostrar seus valores nem iniciar pagamentos; corrija todos os itens marcados antes de prosseguir.

## 2. Cenários do checkout

Use uma janela anônima e a conta de teste comprador. Confirme que o Mercado Pago exibe o valor e o nome do plano corretos:

| Cenário | Valor esperado | Resultado no sistema |
|---|---:|---|
| Compra inicial Essencial | R$ 179,00 | Plano Essencial, até 3 usuários e 12 meses após aprovação/ativação |
| Compra inicial Equipe | R$ 299,00 | Plano Equipe, até 10 usuários e 12 meses após aprovação/ativação |
| Pagamento pendente ou recusado | Conforme checkout | Não ativar nem estender a assinatura |
| Renovação Essencial | R$ 80,00 | Acrescentar 12 meses após aprovação/ativação |
| Renovação Equipe | R$ 120,00 | Acrescentar 12 meses após aprovação/ativação |

Para testar renovação e upgrade, preparar uma assinatura de teste vigente/expirada no banco isolado. Não alterar manualmente datas ou planos em produção.

### Resultados de cartão para homologação

Cada cenário deve começar em um checkout novo, com o mesmo e-mail ainda não cadastrado. No nome do titular do cartão de teste, informe exatamente o código abaixo. O pagamento aprovado de 16/11/2026 já validou o fluxo antigo; ao final desta bateria, faça um novo APRO para validar o cadastro pós-pagamento.

| Titular | Resultado esperado | Efeito esperado no Comércio Cloud |
|---|---|---|
| OTHE | Recusado por erro geral | Sem cadastro de empresa e sem assinatura |
| CONT | Pagamento pendente | Sem cadastro de empresa e sem assinatura |
| CALL | Recusado; requer validação | Sem cadastro de empresa e sem assinatura |
| FUND | Recusado por saldo insuficiente | Sem cadastro de empresa e sem assinatura |
| SECU | Recusado por código de segurança | Sem cadastro de empresa e sem assinatura |
| EXPI | Recusado por vencimento | Sem cadastro de empresa e sem assinatura |
| FORM | Recusado por erro nos dados | Sem cadastro de empresa e sem assinatura |
| APRO | Aprovado | Cadastro da empresa liberado somente após confirmação do webhook |

Para cada tentativa recusada ou pendente, confira o status no retorno e confirme que a tabela de empresas e assinaturas não ganhou um registro novo. Não use o pagamento aprovado anterior para esta validação: ele está vinculado à empresa de teste já criada.
## 3. Cenários de troca de plano

- **Upgrade:** criar assinatura Essencial ativa de teste. A tela mostra uma estimativa proporcional; o Mercado Pago deve exibir o valor efetivo antes da confirmação. Se já houver um checkout válido, a tela deve reutilizar e mostrar o valor daquele checkout. Antes do pagamento, o plano e o limite continuam Essencial; depois da aprovação e ativação, deve virar Equipe, manter a data de vencimento original e liberar até 10 usuários.
- **Downgrade:** criar assinatura Equipe ativa com no máximo 3 membros ativos e vagas reservadas por convites. Agendar Essencial, confirmar a data efetiva, cancelar e reagendar. O downgrade deve ocorrer somente com a renovação aprovada por R$ 80,00.
- **Data da renovação:** preparar uma assinatura vencida e conferir que `current_period_start` usa a data de aprovação (`date_approved`) do pagamento e `current_period_end` fica 12 meses depois. Atraso na entrega do webhook não deve deslocar o início para a data de chegada da notificação. Se existir downgrade agendado, conferir que somente um checkout para o plano Essencial pode efetivá-lo.
- Tentar agendar downgrade com mais de 3 vagas ocupadas/reservadas; a aplicação deve orientar a reduzir acessos ou convites.
- Após expirar uma assinatura com downgrade agendado, confirmar que Administradores podem desativar usuários/revogar convites, mas não reativar usuários nem operar o sistema até renovar.

## 4. Webhook e idempotência

Pagamentos de teste podem não enviar notificações automaticamente. O Mercado Pago documenta a simulação de recebimento do webhook pelo painel da aplicação: selecione a URL de teste, o evento de pagamento e informe o `Data ID` do pagamento. A simulação confere a resposta HTTP do endpoint; para o processamento completo, use um ID de pagamento que a API de teste consiga consultar.

- Simular notificação de pagamento pendente e confirmar que não há ativação.
- Aprovar uma compra de teste e disparar a notificação pelo simulador. Confirmar `payment_events`, assinatura, vigência e estado do painel central.
- Reenviar a mesma notificação. A assinatura não pode ganhar mais 12 meses nem trocar de plano uma segunda vez.
- Com um checkout pendente, enviar uma notificação com referência/plano/etapa/valor divergente; o webhook deve rejeitá-la sem ativar ou estender a assinatura.
- Simular dois pagamentos aprovados diferentes associados à mesma referência de checkout. O primeiro pode ser aplicado; o segundo deve ficar como `duplicate_order`, sem alterar novamente a assinatura, e deve ser encaminhado para conferência de estorno.
- Com `MERCADO_PAGO_ACTIVATE_APPROVED_PAYMENTS=false`, confirmar que o pagamento fica aguardando política e não aplica a mudança. Habilitar em homologação e reenviar a notificação; conferir a aplicação única da mudança.
- Enquanto uma renovação aprovada aguarda ativação, tentar cancelar a troca agendada; a operação deve ser bloqueada para preservar o plano e o valor associados ao pagamento confirmado.
- Conferir assinatura inválida (resposta 401), moeda/valor não correspondente (rejeição sem ativação), falha de API (evento reprocessável) e notificação de outro produto (ignorada).

## 5. Painel central

- Confirmar antes um produto e os planos `comercio-cloud-essencial` e `comercio-cloud-equipe` próprios do Comércio Cloud, com limites e valores iguais aos locais.
- Usar endpoint e segredo de homologação do painel central, se disponíveis. Se o painel não tiver ambiente isolado, manter `SUBSCRIPTION_PANEL_URL` e `SUBSCRIPTION_PANEL_SECRET` vazios durante os testes para evitar atualizar registros reais.
- Após pagamento processado, conferir se o snapshot enviado contém o plano efetivo, status, início/fim de vigência e valor do último pagamento.
- Simular falha do painel e reenviar pelo botão de sincronização; isso não deve repetir cobrança ou estender a vigência.

## 6. Critérios para habilitar produção

- Todos os cenários acima passaram em banco e credenciais de homologação.
- Credenciais de produção e segredo do webhook foram inseridos somente no ambiente privado correto.
- URL pública HTTPS, produto/códigos de plano, limites e preços foram conferidos no painel.
- `MERCADO_PAGO_SANDBOX=false` e `MERCADO_PAGO_ACTIVATE_APPROVED_PAYMENTS=true` só após validação final; o checkout de produção deve ser habilitado por último.
- Registrar o resultado da homologação e preservar o primeiro deploy como mudança separada, com backup e plano de reversão.

## Referências oficiais

- [Contas de teste](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-preferences/test-accounts)
- [Configurar e simular notificações](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-preferences/payment-notifications)
- [Realizar compras de teste](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-preferences/integration-test/test-purchases)
