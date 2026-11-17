# Matriz de paridade — RMC Flow Comércio Local → Cloud

Referência: repositório `LivreComercioBR/RMC-Flow-Com-rcio-Local`, README até 3.10.1, prints do Dashboard/configuração de módulos e código de `BusinessModulesForm.cs`.

Legenda: **Base** = aparece para toda empresa; **Opcional** = aparece quando habilitado em Configurações > Módulos da empresa.

| Área | Disponibilidade | Escopo funcional a preservar na edição Cloud |
|---|---|---|
| Painel/Dashboard | Base | Entradas do dia, resultado mensal, caixa, contas a receber/pagar, estoque baixo e atenção operacional; ocultar/exibir valores; alertas financeiros. Adaptar indicadores conforme módulos ativos. |
| Clientes | Base | Cadastro, edição, pesquisa por nome/documento/telefone/e-mail; seleção e cadastro rápido em fluxos operacionais; histórico de vendas. |
| Produtos/Peças | Base | Cadastro, categorias, busca por nome/código/código de barras, preço, estoque mínimo, prateleira/localização e status. |
| Estoque | Base | Entradas/saídas/ajustes, pesquisa de item e histórico de movimentações; integração com vendas e compras. |
| PDV/Vendas | Base | Venda balcão ou vinculada a cliente, itens, quantidades, descontos e pagamentos; registro financeiro e recibo/histórico. Proteger contra duplicidade em confirmação. |
| Caixa | Base | Abertura/fechamento, entradas, suprimentos e sangrias com descrição, dinheiro físico e resumo diário por forma de pagamento. |
| Financeiro | Base | Contas a pagar/receber, lançamentos manuais, categorias, fornecedor/devedor, parcelas, formas e datas de pagamento, baixa, cancelamento, atraso, fluxo de caixa e alertas. Dinheiro integra o caixa aberto; meios eletrônicos não alteram numerário físico. |
| Compras | Base | Compra com fornecedor/documento/data, vários itens, custos e condições de pagamento; entrada de estoque; contas a pagar em parcelas; cancelamento com estorno de estoque e cancelamento de parcelas pendentes, preservando pagamentos feitos. |
| Fornecedores | Base | Cadastro, edição, pesquisa por nome, documento, telefone, e-mail/contato; seleção e cadastro rápido em compras e contas a pagar. |
| Devedores | Base | Painel administrativo de clientes com contas a receber abertas, saldo total, parcelas vencidas, pesquisa e acesso ao histórico financeiro do cliente; reutiliza o cadastro de clientes. |
| Categorias | Base | Categorias usadas em produtos e financeiro, com criação contextual nos formulários aplicáveis. |
| Relatórios | Base | Filtros hoje/semana/mês/ano/período personalizado; receitas recebidas, despesas, resultado, pendências, vendas por forma de pagamento, itens mais vendidos e estoque baixo. |
| Dados e configurações da empresa | Base | Razão social/nome fantasia, documento, contato/endereço e atividade principal; documentos devem usar esses dados. |
| Usuarios e permissoes | Base | Login, convite, ativacao/desativacao, perfis Administrador/Atendente/Mecanico e alteracao de senha; planos definem Essencial ate 3 usuarios e Equipe ate 10; computadores ilimitados. |
| Auditoria | Base | Eventos relevantes consultáveis, identificados em português, incluindo alterações de usuários, cadastros, vendas, compras, financeiro e operações sensíveis. |
| Backup/restauração | Base | No Cloud, substituir cópia do arquivo local por backup/exportação e restauração com controle de autorização, escopo da empresa, integridade e histórico. |
| Serviços/catálogo | Opcional | Cadastro e pesquisa de serviços; inclusão junto a itens em orçamentos/ordens; criação rápida quando aplicável. |
| Agenda | Opcional | Data/hora, cliente, veículo/objeto atendido, serviço previsto, observações e estados Agendado/Confirmado/Cancelado/Convertido; gerar OS uma única vez; mensagem de WhatsApp revisada pelo usuário, sem envio automático. |
| Orçamentos | Opcional | Cliente, veículo quando aplicável, validade padrão de 10 dias, observações, serviços/peças/itens manuais, pesquisa de catálogo, total; aprovação/reprovação e dados de aprovação; PDF/impressão/WhatsApp. Não movimentar estoque, caixa ou financeiro antes dos fluxos próprios. |
| Ordens de serviço | Opcional | Conversão controlada de orçamento aprovado, cliente e veículo, itens, observações, status operacionais, garantia e documentos; baixa das peças no marco definido pela regra local; recebimento integrado e proteção contra duplicidade. |
| Veículos | Opcional | Cadastro relacionado ao cliente, seleção filtrada pelo cliente, busca por placa/marca/modelo/tipo e histórico de serviços. No Cloud, modelar como veículo/equipamento conforme a operação da empresa, sem restringir o módulo a oficinas. |
| Garantias | Opcional | Prazo e validade associados à OS finalizada; lista de garantias em vigor/vencidas. |

## Regra de ativação dos módulos

- Cada empresa possui sua própria configuração de recursos.
- Configuração inicial: módulo de serviços desativado; menu e dashboard mostram somente o escopo comercial.
- Ao ativar, ficam acessíveis os menus de serviços, agenda, orçamentos/OS e garantias definidos para esse módulo.
- Em vez de exigir reinício como no aplicativo Windows, a versão web atualiza a navegação na sessão após salvar (e revalida permissões no servidor em cada requisição).
- Dados existentes não devem ser apagados ao desativar o módulo; apenas ocultar/bloquear o acesso operacional conforme permissão e política definida.
- A ativação é por empresa; regras de plano/preço para esse módulo ainda precisam ser decididas.

## Andamento da paridade

- **Clientes — primeira versão implementada:** cadastro, edição, busca por nome/documento/telefone/e-mail e ativação/desativação sem apagar o histórico; consultas e gravações sempre usam o `company_id` da empresa autorizada na sessão.
- **Categorias e produtos/peças — primeira versão implementada:** categorias por empresa e finalidade; catálogo com SKU/código de barras, categoria, custo, preço, estoque mínimo e localização; saldo inicial e movimentações de estoque com responsável/motivo, trava transacional e bloqueio de saldo negativo. Histórico não é editável pela interface.
- **Fornecedores e compras — primeira versão implementada:** fornecedores; compras com vários produtos, documento/data e parcelas; entrada de estoque e atualização do custo; contas a pagar com baixa parcial; cancelamento transacional com estorno do estoque, cancelamento do saldo em aberto e preservação dos pagamentos.
- **Caixa - primeira versão implementada:** abertura/fechamento por empresa, saldo contado e divergência, entradas, saídas, suprimentos e sangrias. Baixas de contas a pagar em dinheiro exigem caixa aberto e geram saída vinculada na mesma transação.
- **PDV/Vendas - primeira versão implementada:** venda balcão ou vinculada a cliente, itens com preço/custo do cadastro, desconto geral, pagamento dividido, baixa transacional de estoque, idempotência na confirmação e saldo parcelado em contas a receber. Pagamento em dinheiro entra no Caixa aberto, na mesma transação.
- **Contas a receber - primeira versão implementada:** filtros de pendentes/vencidas/pagas, pagamento parcial, histórico, atualização do saldo da venda e integração do dinheiro com o Caixa.
- **Cancelamento de venda - primeira versão implementada:** registra motivo, autor e data; estorna estoque, cancela apenas saldo de recebíveis em aberto e preserva pagamentos registrados.
- **Devolução de venda cancelada - primeira versão implementada:** registro separado, sem apagar recebimentos; respeita o total líquido recebido. Em espécie exige Caixa aberto e gera saída vinculada.
- **Resumo por forma de pagamento - primeira versão implementada:** filtros por período e consolidação de vendas, parcelas recebidas, devoluções e movimento líquido do caixa físico.
- **Relatórios - primeira versão implementada:** filtro por período, vendas concluídas, recebimentos/devoluções, despesas pagas, pendências, itens mais vendidos e estoque baixo.
- **Lançamentos financeiros manuais - primeira versão implementada:** contas a pagar/receber manuais com parcelas, fornecedor/cliente e categoria opcional. Baixas parciais preservam os fluxos de pagamento/recebimento existentes e a integração do Caixa.
- **Categorias financeiras - primeira versão implementada:** usa o cadastro geral com tipos Receita e Despesa, sem listas paralelas; desativar preserva registros associados. Relatórios agregam contas pagas por categoria.
- **Histórico de clientes - primeira versão implementada:** exibe vendas e parcelas abertas por cliente e oferece início de venda com cliente pré-selecionado.
- **Histórico de fornecedores - primeira versão implementada:** exibe compras e contas a pagar abertas por fornecedor e oferece início de compra com fornecedor pré-selecionado.
- **Compras - filtros e pagamentos:** lista filtrável por fornecedor, status e período, saldo em aberto por compra e histórico de pagamentos por parcela.
- **Dados da empresa - primeira versão implementada:** administrador pode editar identificação, contato, atividade e endereço da empresa ativa; endereço fica em configuração isolada pelo `company_id`.
- **Usuários - gestão inicial:** administrador lista membros vinculados à empresa e altera perfil/status por empresa; o último administrador ativo não pode ser removido. Convites e redefinição de senha pendentes.
- **Convites e primeiro acesso:** administrador convida por e-mail; token aleatório de uso único com validade de 7 dias; usuário novo define senha, conta existente confirma sua senha; administrador pode revogar convite. Exige serviço de e-mail de entrega configurado.
- **Acesso por perfil - primeira política:** Administrador mantém acesso completo; Atendente acessa clientes, consulta produtos, PDV e caixa, podendo solicitar cancelamento/devolução mediante senha de administrador; Mecânico fica restrito ao Dashboard até existirem rotas de serviço, e dependerá do módulo ativo.
- **Autorização de cancelamento/devolução:** identidade do solicitante e do administrador autorizador registrada. A devolução limita-se ao saldo recebido na forma original. O estorno em dinheiro sai do caixa da empresa que estiver aberto no momento da devolução; caixas fechados não são reabertos nem alterados.
- **Auditoria do caixa - devoluções:** detalhes do caixa identificam recebimentos/devoluções de vendas; devolução mostra solicitante, administrador autorizador e motivo e entra no saldo esperado e no fechamento.
- **Auditoria - versão inicial:** eventos por empresa com filtros de evento/usuário/período; trilha registra configurações da empresa, módulos, perfis de acesso, abertura/movimentação/fechamento de caixa e cancelamentos/devoluções, incluindo ator, autorizador, metadados, IP e agente do navegador.
- **Catálogo de serviços - primeira versão implementada:** quando o módulo está ativo, Administrador cadastra/edita e ativa/desativa serviços; Mecânico consulta o catálogo. Registros são isolados por empresa, alterações são auditadas e não há exclusão física. A migração `000014` cria a tabela do catálogo; ainda não foi executada.
- **Agenda - primeira versão implementada:** registra cliente, serviço e preço fotografados, data/hora, observações e estados Agendado/Confirmado/Cancelado/Convertido; permite reagendar e consultar por período, situação e cliente/serviço. Cancelamentos e alterações são auditados; dados e operações respeitam a empresa e o módulo ativo. A migração `000015` ainda não foi executada. A conversão via orçamento aprovado está protegida contra duplicidade.
- **Orçamentos - primeira versão implementada:** rascunho vinculado a cliente e, opcionalmente, agendamento; aceita serviços, produtos/peças e itens manuais com preços e totais fotografados, validade padrão de 10 dias, edição enquanto rascunho, registro de envio, aprovação/reprovação com contato/canal, impressão/Salvar PDF e mensagem revisável no WhatsApp. Não movimenta estoque, caixa ou financeiro. A migração `000016` ainda não foi executada.
- **Ordens de serviço - primeira versão implementada:** conversão transacional única a partir de orçamento aprovado; guarda itens e preços fotografados, dados do equipamento, relato, diagnóstico, andamento e garantia. Baixa de peças ao concluir serviço e estorno ao cancelar após baixa, com proteção contra duplicidade e auditoria. Ao encaminhar para pagamento, cria conta a receber única; aceita pagamentos parciais pelos meios existentes e exige caixa aberto para dinheiro. Pagamento integral libera a OS para retirada. Cancelamento exige senha de administrador ou liberação do administrador, estorna estoque e encerra saldo não pago. Devoluções são lançamentos separados, limitados aos valores recebidos por forma; em dinheiro, exigem caixa aberto e saldo físico suficiente. Migrações `000017`–`000019` ainda não foram executadas.
- **Veículos e equipamentos - primeira versão implementada:** cadastro reutilizável por cliente com tipo, nome, fabricante, modelo, ano, cor, placa/série, observações e estado ativo/inativo. A abertura da OS pode selecionar um cadastro existente ou salvar um novo; cada OS mantém uma fotografia dos dados e exibe o histórico associado ao veículo/equipamento. Migração `000018` ainda não foi executada.
- **Módulos da empresa - configuração inicial:** administrador pode ativar/desativar a preferência de prestação de serviços, registrada por empresa; rotas do catálogo ficam bloqueadas enquanto o módulo está desligado.
- Próximo: ampliar a consulta de garantias por período/validade e homologar o fluxo de cancelamento/devolução.

## Diferenças de implementação Cloud

- Acesso online por múltiplos computadores sem limite de PCs por assinatura.
- Banco Cloud próprio `rona7916_rmc_flow_comercio_cloud`; dados de cada empresa isolados por contexto confiável do servidor.
- Assinatura anual Mercado Pago: Essencial (ate 3 usuarios), R$ 179,00 inicial/R$ 80,00 renovacao; Equipe (ate 10 usuarios), R$ 299,00 inicial/R$ 120,00 renovacao. Computadores ilimitados. Politicas de tolerancia, suspensao e cancelamento ainda pendentes.
- Login, sessão, permissões, auditoria, exportação e recuperação adaptados a uma aplicação web multiempresa.
- Preservar histórico financeiro e imutabilidade apropriada: cancelamentos e estornos registrados, não apagar transações pagas.

## Próxima etapa

Revisar paridade dos fluxos existentes com a edição Local e avançar em usuários/permissões e módulos da empresa. Operações devem continuar usando o contexto autorizado do servidor, sem confiar em identificadores de empresa enviados pelo navegador.
