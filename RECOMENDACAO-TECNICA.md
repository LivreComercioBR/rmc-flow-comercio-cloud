# Recomendação técnica — Comércio Cloud

**Status: recomendação para orientar a implementação; não é registro de que o framework já está instalado em produção.**

## Stack recomendada

- **Aplicação:** Laravel 13, PHP 8.4.
- **Banco:** MySQL 5.7 do banco exclusivo `rona7916_rmc_flow_comercio_cloud`.
- **Hospedagem:** domínio `sistema.comercio.cloud.rmcsites.com.br`, com aplicação conforme o caminho informado pelo usuário e arquivos sensíveis fora da raiz pública em `/home1/rona7916/private/rmc_flow_comercio_cloud/`.
- **Arquitetura:** aplicação multiempresa em banco compartilhado exclusivo do Comércio Cloud, com escopo por empresa obrigatório no backend.

## Base de compatibilidade

A documentação oficial do Laravel 13 lista suporte a PHP 8.3–8.5 e MySQL 5.7 ou superior. Assim, PHP 8.4 e MySQL 5.7 informados para o ambiente são compatíveis com os requisitos documentados. Fontes: [Laravel 13 — Database](https://laravel.com/docs/13.x/database) e [Laravel 13 — Release Notes](https://laravel.com/docs/13.x/releases).

A página pública de compatibilidade da HostGator consultada lista Laravel 10 e 11 para hospedagem compartilhada, embora a hospedagem informe PHP 8.4. Por isso, validar Composer, extensões PHP, comandos permitidos, limites de execução e processo de publicação em uma instalação isolada antes de enviar a aplicação ao domínio. Fonte: [Compatibilidades da HostGator](https://suporte.hostgator.com.br/hc/pt-br/articles/30811116692115-Quais-s%C3%A3o-as-compatibilidades-da-HostGator).

## Regras para a fundação

1. Resolver o `company_id` ativo pela sessão e membership autenticados no servidor.
2. Escopar query, escrita, anexos, exportação e rotas pelo tenant; não aceitar um `company_id` arbitrário enviado pelo cliente.
3. Usar transações nos fluxos que afetam simultaneamente venda/estoque/caixa/financeiro e compra/estoque/contas.
4. Usar migrations versionadas; evitar criação manual divergente de tabelas em produção.
5. Tratar valores monetários com `DECIMAL` exato e regras explícitas de arredondamento por centavo.
6. Em MySQL 5.7, não depender de `CHECK` constraints para regras de negócio; validar no domínio/backend e usar chaves estrangeiras, índices únicos e transações onde suportados.
7. Guardar configuração e segredos do banco/Mercado Pago em arquivo fora do document root, no diretório privado informado, sem incluí-los no Git.
8. Manter integrações do produto Oficina Cloud isoladas; integrar ao painel central somente por contrato autenticado e operações aditivas.

## Primeira entrega técnica proposta

Criar esqueleto Laravel em ambiente local, configuração de conexão do Comércio Cloud sem credenciais reais, migrations iniciais para `companies`, `users`, `company_memberships`, `company_modules`, catálogo de planos e assinaturas/eventos; implementar autenticação e middleware de tenant antes dos módulos de vendas e estoque. Validar build e migrations em banco local ou ambiente de homologação, nunca no banco de produção nesta etapa.

## Premissas ainda abertas

- Confirmar se o plano HostGator atual disponibiliza Composer e extensões exigidas pelo Laravel 13 no domínio novo.
- Definir repositório GitHub do Comércio Cloud.
- Definir regras comerciais próprias (planos, valores, limite de usuários, tolerância, suspensão e cancelamento).
- Confirmar contrato técnico com o painel central de assinaturas.

## Atualização do scaffold (13/11/2026)

A recomendação tornou-se a base de código local: scaffold Laravel Framework **13.42.0**, dependências registradas em `composer.lock`, execução local em PHP 8.5.9 e configuração de exemplo preparada para o banco MySQL exclusivo do Comércio Cloud. Também foram adicionados models e migration de empresas, memberships, módulos, configurações, planos, assinaturas e eventos de pagamento, além do middleware e trait fail-closed de escopo por empresa.

Isso não significa que a aplicação foi implantada. A HostGator ainda precisa ser validada com Laravel 13; nenhuma credencial real foi configurada e nenhuma migration foi executada contra o banco de produção.

## Ambiente confirmado pelo phpMyAdmin (print do usuário)

- PHP da hospedagem: **8.4.27**.
- Banco: **Percona Server 5.7.44-48**, protocolo 10, charset do servidor `utf8`.
- Extensões listadas na tela: `mysqli`, `curl` e `mbstring`.
- Ainda falta confirmar no PHP da hospedagem as extensões requeridas pelo Laravel, especialmente `PDO` e o driver `pdo_mysql`, além de DOM, Fileinfo, Filter, Hash, OpenSSL, PCRE, Session, Tokenizer e XML.
- Conclusão: versões de PHP e banco atendem aos requisitos documentados do Laravel 13; compatibilidade de extensões e publicação continua pendente de verificação na conta HostGator.
