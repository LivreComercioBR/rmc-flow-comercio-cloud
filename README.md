# RMC Flow Comércio Cloud

Aplicação online de gestão para comércio em geral, com módulos opcionais de prestação de serviços por empresa.

## Stack inicial

- Laravel Framework 13.42 (Composer lock)
- PHP da hospedagem: 8.4.27; desenvolvimento local atual em PHP 8.5.9
- Percona Server 5.7.44-48 (MySQL compatível), usando o banco exclusivo `rona7916_rmc_flow_comercio_cloud`
- Aplicação multiempresa com isolamento lógico por `company_id`

## Ambiente de desenvolvimento

1. Use PHP 8.4.x para aproximar o ambiente da hospedagem, Composer e Node.js/NPM.
2. Ative `pdo_mysql` no `php.ini` do PHP usado pelo terminal e confirme que `php -m` o lista.
3. Crie um banco local separado chamado `rmc_flow_comercio_cloud_dev`; nao use o banco da HostGator.
4. Execute `composer setup` para instalar dependencias e compilar os assets; ele nao gera chave nem aplica migracoes.
5. Confira o `.env` local: `DB_DATABASE=rmc_flow_comercio_cloud_dev`, host e credenciais locais. Se `APP_KEY` estiver vazio, gere a chave local com `php artisan key:generate`; depois execute manualmente `php artisan migrate`.

O arquivo `.env.example` usa o nome do banco local de desenvolvimento. Nunca coloque credenciais de producao neste workspace nem envie arquivos `.env` ao Git. Na hospedagem, segredos devem permanecer fora da raiz publica em `/home1/rona7916/private/rmc_flow_comercio_cloud/`.

## Hospedagem prevista

- Aplicação: `/home1/rona7916/sistema.comercio.cloud.rmcsites.com.br/`
- Arquivos sensíveis: `/home1/rona7916/private/rmc_flow_comercio_cloud/`
- Banco: `rona7916_rmc_flow_comercio_cloud`

O print do phpMyAdmin confirma PHP 8.4.27 e Percona Server 5.7.44-48. A tela lista `mysqli`, `curl` e `mbstring`, mas ainda não confirma `PDO`/`pdo_mysql` e todas as extensões exigidas pelo Laravel 13. Conferir esses módulos e o processo de publicação em homologação antes do primeiro deploy. Não há conexão com a hospedagem ou migração executada contra produção neste estágio.

## Multiempresa

`EnsureCompanyContext` exige empresa ativa na sessão e confirma membership ativa antes de popular `CompanyContext`. O trait `ScopesToCompany` fornece escopo global fail-closed para futuros modelos operacionais com `company_id`; use-o nesses modelos e não permita remover o escopo em operações acionadas pelo usuário.

## Estado

A fundacao inclui empresas, equipes, planos, assinaturas e eventos de pagamento. Planos anuais definidos: Essencial, R$ 179,00 na primeira compra e R$ 80,00 na renovacao, ate 3 usuarios; Equipe, R$ 299,00 na primeira compra e R$ 120,00 na renovacao, ate 10 usuarios. Ambos permitem computadores ilimitados. As variáveis de exemplo mantêm checkout e ativação automática desligados por padrão. A hospedagem foi usada com credenciais de sandbox para testes controlados; isso não equivale à liberação de vendas em produção.


## Implementado nesta etapa

- Cadastro inicial de empresa e administrador, com ativação inicial do módulo comercial e serviços desabilitados.
- Login com limitação de tentativas, encerramento de sessão e seleção da empresa autorizada.
- Middleware que resolve a empresa ativa pela sessão e valida membership antes do Dashboard.
- Interface inicial responsiva em Blade.
- Primeiro módulo operacional Cloud: cadastro de clientes com pesquisa, edição, ativação/desativação e isolamento por empresa; desativar preserva os dados para o histórico.
- Catálogo inicial: categorias por empresa, produtos/peças com SKU e código de barras, custo e preço, mínimo/localização, pesquisa e alerta de estoque baixo.
- Entradas, saídas e ajustes por contagem atualizam o saldo sob trava transacional e criam histórico de movimentações com motivo e responsável; não permitem estoque negativo e não oferecem edição/exclusão do histórico pela tela.
- Dashboard apresenta contagem real de clientes ativos, produtos ativos e itens com estoque baixo.
- Fornecedores e compras: cadastro de fornecedores, compra com itens múltiplos, atualização de estoque/custo e geração de parcelas a pagar com rateio exato dos centavos.
- Parcelas aceitam pagamentos parciais por Pix, cartão, transferência ou outro meio. Cancelar compra estorna estoque em uma única transação, cancela apenas o saldo ainda aberto e preserva baixas já registradas; se não houver estoque suficiente para o estorno, o cancelamento é revertido.
- Caixa: abertura/fechamento, saldo contado, divergência, entradas/saídas, suprimentos e sangrias. Pagamentos de contas a pagar em dinheiro exigem caixa aberto e lançam uma saída vinculada dentro da mesma transação financeira.
- PDV/Vendas: registro idempotente com itens e preço/custo fotografados, desconto geral, baixa transacional de estoque, pagamentos divididos por forma, entrada de dinheiro vinculada ao Caixa aberto e geração parcelada de contas a receber para o saldo.
- O lancamento manual de receita permite cadastrar um cliente/devedor e retornar ao formulario com o cadastro selecionado.
- Contas a receber: filtros de abertas, vencidas e pagas, baixa parcial idempotente, descricao preservada nos lancamentos manuais e historico de pagamentos. O historico do cliente abre as parcelas vinculadas para consulta e recebimento; dinheiro integra o Caixa.
- Devedores: painel administrativo por cliente com saldo de contas a receber em aberto, parcelas vencidas e atalho ao historico financeiro; reutiliza o cadastro de clientes existente.
- Cancelamento de venda: motivo e autor registrados; estorno de estoque e cancelamento do saldo ainda em aberto feitos na mesma transação. Pagamentos e saldos já recebidos permanecem no histórico.
- Devoluções de vendas canceladas: registro separado dos pagamentos originais, validação do total líquido recebido, motivo, responsável e referência. Em dinheiro exige Caixa aberto, saldo disponível e lança saída vinculada.
- Resumo por forma de pagamento: filtros por hoje, semana, mês, ano e período personalizado; consolida pagamentos de vendas, recebimentos de parcelas, devoluções e entradas/saídas do Caixa.
- Relatórios: filtro de período; vendas concluídas, recebimentos, devoluções, despesas pagas, pendências, itens mais vendidos e alertas de estoque baixo.
- Lançamentos financeiros manuais: despesas e receitas em parcelas, com vínculo opcional de fornecedor/cliente e categoria financeira separada entre receita e despesa; recebimentos e pagamentos usam os fluxos existentes e integram o Caixa quando em dinheiro.
- Categorias financeiras: utilizam o cadastro geral existente (tipos Receita e Despesa), evitando listas paralelas; ativação/desativação preserva histórico e relatórios exibem totais pagos por categoria.
- Histórico do cliente: página individual com vendas, status, valores recebidos e parcelas pendentes; permite iniciar uma nova venda já vinculada ao cliente.
- Histórico do fornecedor: página individual com compras, status, documentos e contas a pagar abertas; permite iniciar uma compra com o fornecedor pré-selecionado.
- Compras: filtros por fornecedor, status e período, saldo pendente agregado e detalhamento do histórico de pagamentos de cada parcela.
- Configurações da empresa: administrador edita dados fiscais/comerciais, contato, atividade e endereço da empresa ativa; endereço armazenado em `company_settings` sob o escopo da empresa.
- Usuários: tela administrativa para listar membros existentes e alterar perfil/status no escopo da empresa; protege a permanência de pelo menos um administrador ativo. Convites e recuperação de senha implementados.
- Convites de usuários: administrador envia convite para Atendente, Mecânico ou Administrador; link aleatório de uso único expira em 7 dias. Novo usuário cria conta com senha, usuário existente confirma a senha; aceite ativa o vínculo na empresa. Convites podem ser revogados e os eventos ficam na Auditoria.
- Envio de convites exige um mailer de entrega real configurado. Mailers `log` e `array` são bloqueados para evitar links secretos em logs ou mensagens que não chegam ao destinatário.
- Verificacao de e-mail: link assinado e temporario, reenvio com limite, envio bloqueado para mailers de log/array; o acesso operacional e a contratacao exigem e-mail confirmado. Convite de uso unico confirma o endereco do destinatario.
- Recuperação de senha: solicitação com resposta que não revela se o e-mail existe, token temporário armazenado na tabela `password_reset_tokens`, validade de 60 minutos, limitação de tentativas e redefinição com senha mínima de 10 caracteres. Requer mailer SMTP/entrega real; `log` e `array` são bloqueados.
- Acesso inicial por perfil: Administrador tem acesso completo; Atendente usa clientes, consulta de produtos, PDV e caixa, e pode solicitar cancelamentos/devoluções mediante confirmação da senha de administrador; Mecânico acessa rotas de serviço somente quando o módulo estiver habilitado para a empresa.
- Cancelamentos e devoluções registram solicitante e autorizador. A devolução respeita o saldo recebido pela forma original; dinheiro movimenta o caixa da empresa aberto no momento, sem alterar caixas fechados. A tela oferece somente formas com saldo disponível.
- Detalhes de caixas abertos/fechados identificam recebimentos e devoluções de vendas; devoluções exibem solicitante, autorizador e motivo, e compõem os totais e o fechamento do caixa.
- Auditoria por empresa: painel administrativo filtrável por evento, usuário e período; registra alterações de dados/módulos/perfis, operações de caixa e cancelamentos/devoluções, com ator, autorizador, metadados e origem da requisição.
- Catálogo de serviços: com o módulo ativado, Administrador cadastra/edita e ativa/desativa serviços; Mecânico pode consultar. Dados são isolados por empresa e alterações auditadas. Migração `000014` consta como executada na hospedagem, conforme a listagem enviada em 19/11/2026.
- Agenda de serviços: registra cliente, serviço/preço fotografados, data/hora, observações e estados Agendado/Confirmado/Cancelado/Convertido; permite reagendar e filtrar registros. Migração `000015` consta como executada na hospedagem, conforme a listagem enviada em 19/11/2026.
- Orçamentos: rascunhos com validade de 10 dias, cliente e agendamento opcional, itens de serviço/produto/manual, preços preservados, edição até envio, aprovação/reprovação auditada, impressão/Salvar PDF e texto de WhatsApp revisável. Não geram movimentos financeiros ou de estoque. Migração `000016` consta como executada na hospedagem, conforme a listagem enviada em 19/11/2026.
- Ordens de serviço: conversão transacional única de orçamento aprovado, com snapshot de itens, veículo/equipamento, etapas operacionais, diagnóstico e garantia. Conta a receber única; pagamentos parciais reutilizam o fluxo financeiro, dinheiro exige caixa aberto e pagamento integral libera a retirada. Cancelamento exige autorização, estorna peças e encerra eventual saldo aberto. Devoluções financeiras são registradas por forma de pagamento, com limite pelo recebido; dinheiro exige caixa aberto e saldo disponível. Migrações `000017`–`000019` constam como executadas na hospedagem, conforme a listagem enviada em 19/11/2026.
- Veículos/equipamentos: cadastro reutilizável associado ao cliente, com ativação/desativação e histórico das OS; a OS conserva os dados do item como fotografia no momento da abertura. Migração `000018` consta como executada na hospedagem, conforme a listagem enviada em 19/11/2026.
- Próxima etapa: concluir os testes manuais de permissões e isolamento entre empresas, confirmar entrega real dos e-mails transacionais e finalizar upgrade/downgrade em sandbox.

## Estado atual de homologação — 19/11/2026

- **Migrações:** a listagem da hospedagem mostra as migrações como `Ran`, incluindo `2026_11_18_160000_add_intake_fields_to_service_work_orders_table`. A lista enviada também mostra as migrações de catálogo, agenda, orçamentos, OS e veículos executadas.
- **Pagamentos:** compra inicial aprovada, pagamentos recusados/pendentes e renovação antecipada Equipe foram testados em sandbox. A renovação de R$ 120,00 foi processada após corrigir a associação do webhook; o painel confirmou vigência até 17/11/2028.
- **Webhook:** associa pagamentos à assinatura por `subscription_id` e `company_id` dos metadados e permite reavaliar eventos `ignored` após correção, sem reaplicar eventos `processed`.
- **E-mail:** verificação, convite e recuperação usam o mailer padrão do Laravel. O exemplo do repositório usa `MAIL_MAILER=log`; a hospedagem foi configurada com SMTP conforme informado, mas a entrega de recuperação/convites ainda precisa ser confirmada ponta a ponta. Não registrar senhas ou tokens neste repositório.
- **Permissões e isolamento:** o código valida empresa ativa e vínculo ativo pela sessão e aplica escopo de empresa nos modelos operacionais. A homologação manual entre duas empresas e os perfis Administrador/Atendente/Mecânico ainda está pendente.
- **Automação:** os testes automatizados no repositório ainda são exemplos e não há execução de CI registrada. Não considerar a segurança multiempresa homologada apenas pela revisão do código.
- **Planos:** o fluxo de upgrade e a efetivação do downgrade agendado ainda precisam de teste em contas de sandbox. Também é necessário corrigir a aplicação imediata do plano de downgrade quando a renovação é paga antecipadamente, para respeitar a data efetiva exibida ao cliente.

O checkout e a ativação permanecem desligados por padrão no `.env.example`. A hospedagem foi usada em sandbox; a venda com credenciais de produção não está homologada.

## Cobrança e painel central (preparação)

- Webhook do Comércio Cloud: `POST /mercado-pago/webhook`; não altera nem substitui o endpoint de Oficina Cloud.
- O endpoint de webhook é excluído da validação CSRF do navegador, mas exige a assinatura HMAC do Mercado Pago antes de aceitar a notificação.
- A assinatura exige `ts` numérico e `v1` hexadecimal SHA-256 válido; não impõe limite de idade ao timestamp, pois o Mercado Pago informa que pode repetir notificações a cada 15 minutos e continuar as tentativas. A proteção contra repetição é feita pela idempotência do pagamento.
- Notificações de pagamentos ainda pendentes ou com falha técnica podem ser reprocessadas após consulta atualizada à API do Mercado Pago. Um pagamento já processado nunca estende o período novamente; notificações repetidas podem apenas retomar a sincronização pendente com o painel central.
- Se uma confirmação ficar em `awaiting_billing_policy`, uma nova notificação só a ativa quando `MERCADO_PAGO_ACTIVATE_APPROVED_PAYMENTS=true`; o pagamento é consultado novamente e a transição mantém a trava transacional por assinatura.
- Segredos são lidos de `MERCADO_PAGO_ACCESS_TOKEN` e `MERCADO_PAGO_WEBHOOK_SECRET`; a ativação exige explicitamente `MERCADO_PAGO_ACTIVATE_APPROVED_PAYMENTS=true` depois de definida a regra de cobrança.
- A criação de checkout requer `MERCADO_PAGO_NOTIFICATION_URL` como URL pública HTTPS; essa exigência é conferida antes de criar a preferência.
- Preferencias Checkout Pro expiram em tres dias; cliques repetidos no mesmo ciclo reutilizam o checkout ainda valido.
- O ambiente sandbox/produção fica registrado junto ao checkout salvo; ao alternar o ambiente, um link antigo não é reutilizado.
- Cada pagamento confirmado registra o estado da sincronização com o painel central. Se o envio falhar ou ficar interrompido, um Administrador pode reenviar o snapshot na página de assinatura sem repetir a cobrança ou estender novamente o período.
- A sincronização preparada usa `SUBSCRIPTION_PANEL_URL`, `SUBSCRIPTION_PANEL_PRODUCT_CODE` e `SUBSCRIPTION_PANEL_SECRET`, com assinatura HMAC e evento deduplicável para `api/subscription-events.php`.
- O código padrão do produto é `rmc-flow-comercio-cloud`; o mesmo código deve estar cadastrado no painel central e pode ser substituído pela configuração privada `SUBSCRIPTION_PANEL_PRODUCT_CODE`.
- O roteiro passo a passo de homologação sandbox está em `HOMOLOGACAO-MERCADO-PAGO.md`. Pagamentos de teste podem exigir simulação manual da notificação pelo painel Developers.
- Antes de testar, rode `php artisan mercado-pago:preflight`; o comando confere o ambiente sem imprimir segredos ou criar cobranças.
- Pre-requisito no painel central: cadastrar o produto Comercio Cloud e os planos comercio-cloud-essencial e comercio-cloud-equipe com limites e valores iguais aos planos locais.
- Planos anuais: Essencial (ate 3 usuarios), R$ 179,00 inicial e R$ 80,00 renovacao; Equipe (ate 10 usuarios), R$ 299,00 inicial e R$ 120,00 renovacao. No painel central, cadastrar os codigos comercio-cloud-essencial e comercio-cloud-equipe com os mesmos limites e precos.
- O checkout permite escolher Essencial ou Equipe antes do primeiro pagamento; depois o plano fica vinculado a assinatura e renova pelo valor correspondente. Assinaturas vigentes nao iniciam nova cobranca.
- O checkout preparado usa preferência hospedada do Mercado Pago com cobrança anual avulsa e renovação iniciada pelo cliente; esta é a hipótese operacional adotada a partir da regra comercial informada. A integração permanece desligada por padrão. A extensão de 12 meses após pagamento só ocorre com ativação explícita; tolerância, cancelamento e suspensão ainda precisam de política definida.

## Mudanças de plano e estados do pagamento

- Upgrade de Essencial para Equipe pode ser solicitado por um Administrador durante a vigência. A cobrança é proporcional à diferença dos valores do ciclo atual e ao tempo restante. O plano e o limite de 10 usuários só mudam depois de o webhook confirmar o pagamento e a ativação estar habilitada; o vencimento original é preservado.
- Downgrade de Equipe para Essencial é agendado para a próxima renovação. A empresa precisa estar dentro do limite de 3 usuários, contando membros ativos e convites que reservam vagas. Convites e reativações respeitam o limite agendado.
- Após o vencimento, Administradores ainda podem abrir Usuários e acessos para desativar membros ou revogar convites. As operações do sistema continuam bloqueadas até a renovação; reativar usuários exige assinatura vigente.
- Checkouts gerados pela versão atual guardam a referência, etapa, plano e valor esperados. O webhook compara esses dados com o pagamento consultado antes de aplicar a mudança correspondente. Pagamentos aprovados enquanto a ativação estiver desabilitada ficam aguardando política e não liberam mudança de plano até reprocessamento autorizado.
- Uma referência de checkout aprovada só pode estender ou alterar a assinatura uma vez, mesmo que o Mercado Pago reporte outro ID de pagamento para a mesma compra. O evento repetido fica registrado como `duplicate_order` para conferência de estorno; o sistema não realiza estorno automático.
- A sincronização com o painel central envia o snapshot do plano efetivo, vigência e último pagamento confirmado. A integração real do produto Comércio Cloud ainda depende de homologação sandbox e conferência do contrato no painel.

## Publicação e reversão na HostGator

A raiz do projeto na hospedagem é `/home1/rona7916/sistema.comercio.cloud.rmcsites.com.br/`. O arquivo `.env` e os segredos permanecem na configuração privada.

### Publicar uma atualização

1. Faça backup do banco e dos arquivos alterados; confirme no terminal que está na raiz correta.
2. Confira `git status --short` e a revisão que será publicada.
3. Atualize o código com `git pull origin main`.
4. Se `composer.lock` mudou, execute `composer install --no-dev --optimize-autoloader`.
5. Se houver migrações novas, revise-as e faça backup do banco antes de `php artisan migrate --force`. Não execute rollback automático em produção.
6. Limpe os caches com `php artisan optimize:clear` e confira login, painel, assinatura e logs.

### Reverter uma publicação

- Para alteração somente de código, reverta o commit no GitHub e depois execute `git pull origin main` na hospedagem.
- Se a publicação incluiu migração ou alteração de dados, não reverta o esquema às cegas. Restaure o backup compatível ou publique uma migração corretiva revisada.
- Registre a revisão aplicada e o resultado da conferência. Nunca substitua o `.env` privado por um arquivo versionado.
