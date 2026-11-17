# RMC Flow Comércio Cloud — Especificação inicial

## Objetivo

Criar a versão online do RMC Flow Comércio, preservando as funcionalidades da edição Local e adaptando interface, segurança, armazenamento e acesso ao ambiente Cloud. O produto atende comércio em geral e pode habilitar recursos de prestação de serviços por empresa.

## Referência da edição Local

Repositório: https://github.com/LivreComercioBR/RMC-Flow-Com-rcio-Local

O README da edição Local documenta módulos comerciais e operacionais, recursos opcionais de serviços, controle de usuários, auditoria, documentos e rotinas de backup. A configuração de módulos mostrada pelo usuário permite selecionar se a empresa presta serviços e utiliza ordens de serviço; quando ativada, ficam disponíveis serviços, agendamentos, ordens de serviço e garantias. No Cloud, a ativação deve refletir na navegação sem exigir reinício do navegador/aplicativo.

### Núcleo comercial

- Dashboard com entradas, resultado, caixa, contas a receber e pagar, estoque baixo e alertas.
- Clientes.
- Produtos/peças, categorias, pesquisa por nome/código/código de barras, preço, estoque, prateleira/localização.
- Estoque e histórico de movimentações, inclusive entradas por compras.
- PDV: venda de balcão ou vinculada a cliente, itens, formas de pagamento e histórico/recibo.
- Caixa: abertura/fechamento, entradas, suprimentos, sangrias e resumo diário por forma de pagamento.
- Financeiro: contas a pagar/receber, fornecedores e devedores, categorias, parcelas, baixas, cancelamentos, vencimentos e alertas.
- Compras: fornecedor, documento, múltiplos itens, condições/parcelas, integração de estoque e contas a pagar, cancelamento com estorno e preservação do histórico de parcelas pagas.
- Relatórios: períodos predefinidos e personalizados, receitas, despesas, resultado, pendências, vendas por forma de pagamento, itens mais vendidos e estoque baixo.

### Módulo opcional de prestação de serviços

Ativado nas configurações da empresa. Inclui serviços/catálogo, agenda, orçamentos, ordens de serviço, garantias e recursos relacionados. Orçamento não deve movimentar estoque/caixa/financeiro até a conversão e conclusão dos fluxos correspondentes. Aplicar as regras de baixa de estoque e recebimento documentadas na edição Local.

### Administração e segurança por empresa

- Dados e configurações da empresa.
- Usuários, perfis e permissões; no Local existem Administrador, Atendente e Mecânico, a mapear para as necessidades do Cloud.
- Minha senha, redefinição administrativa e recuperação de acesso com fluxo seguro.
- Auditoria legível em português.
- Privacidade para ocultar valores financeiros no Dashboard.
- Backup/exportação e restauração, adaptados para serviço online.

## Decisões e ambiente informados

- Nome do banco: `rona7916_rmc_flow_comercio_cloud`.
- Diretório da aplicação na hospedagem: `/home1/rona7916/sistema.comercio.cloud.rmcsites.com.br/`.
- Diretório privado para arquivos sensíveis: `/home1/rona7916/private/rmc_flow_comercio_cloud/`.
- Cada empresa deve ter seus dados isolados; decisão inicial recomendada: banco compartilhado exclusivo do Comércio Cloud com escopo de empresa aplicado a todos os registros. Banco separado por empresa pode ser reavaliado se escala/necessidade justificar.
- Acesso Cloud sem limite de computadores. Essencial: ate 3 usuarios, R$ 179,00 inicial e R$ 80,00 na renovacao anual. Equipe: ate 10 usuarios, R$ 299,00 inicial e R$ 120,00 na renovacao anual. O limite inclui o administrador.
- Cobrança por assinatura anual via Mercado Pago.
- Existe painel de assinaturas e webhook em produção no RMC Flow Oficina Cloud: `https://app.rmcsites.com.br/mercado-pago/webhook`. Devem ser avaliados para integração/reuso sem misturar dados, planos, regras ou banco do produto Oficina. Checkout hospedado e processamento idempotente de notificações são referências do fluxo existente.
- Precos anuais: Essencial R$ 179,00 inicial/R$ 80,00 renovacao; Equipe R$ 299,00 inicial/R$ 120,00 renovacao. O checkout usa renovacao iniciada pelo cliente e fica desligado ate homologacao.

## Plano de trabalho

1. **Levantamento de paridade:** conferir telas, ações e regras no código da Local, produzir matriz Local → Cloud e identificar itens opcionais.
2. **Arquitetura e modelo de dados:** banco Cloud próprio, isolamento por empresa, autenticação, papéis, auditoria, módulos habilitados e migrações.
3. **Fundação da aplicação:** configuração segura para hospedagem, sessão, login, empresa e dashboard adaptativo.
4. **Cadastros do núcleo:** empresa, usuários, clientes, fornecedores, devedores, categorias e produtos.
5. **Estoque e compras:** movimentações, histórico, entrada de compra, parcelas e estornos.
6. **PDV e caixa:** venda, pagamentos, recebimentos, abertura/fechamento e conciliação das movimentações.
7. **Financeiro e relatórios:** contas, filtros, parcelas, baixas, alertas e relatórios gerenciais.
8. **Módulo de serviços opcional:** catálogo, agenda, orçamentos, OS, etapas, documentos e garantias.
9. **Assinaturas Mercado Pago:** cadastro de produto/planos próprios, checkout, webhook, ativação, renovação, cancelamento e política de inadimplência; integrar ao painel central preservando o isolamento do Oficina Cloud.
10. **Migração e operação:** avaliar importação dos dados Local, exportação/backup, suporte e documentação.
11. **Validação e lançamento:** conferir paridade, isolamento entre empresas, permissões, pagamento/webhook, fluxos críticos e implantação.

## Critérios transversais

- Toda consulta e gravação de dado operacional deve respeitar a empresa autenticada; nunca confiar em `empresa_id` recebido livremente do navegador.
- Separar claramente dados e configurações do Comércio Cloud dos produtos Oficina Cloud e Local.
- Valores monetarios devem usar precisao decimal e regras de arredondamento por centavo.
- Valores monetários devem usar precisão decimal e regras de arredondamento por centavo.
- Alterações de assinatura devem depender de confirmação válida do Mercado Pago, com tratamento idempotente do webhook.
- Não armazenar credenciais ou arquivos sensíveis na raiz pública; usar o diretório privado informado.

## Questões comerciais em aberto

- Planos e limites de usuários/recursos; preço inicial e de renovação definidos conforme acima.
- Período de teste/garantia e tolerância após vencimento.
- Renovação automática ou pagamento anual iniciado pelo cliente.
- Política de acesso após inadimplência/cancelamento e exportação dos dados.
- Se compra do Cloud incluir módulo de serviços ou se este será controlado por plano/adicional.
