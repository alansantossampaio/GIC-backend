# Arquitetura — Sistema de Gerenciamento de Condomínios

**Status:** versão inicial para orientar a implementação  
**Stack prevista:** PHP 8.5, Laravel 13  
**Modelo:** aplicação multi-tenant com vários condomínios na mesma instalação.

> Este documento registra decisões já tomadas e recomendações. Os itens marcados como pendentes precisam ser confirmados antes de migrations definitivas.

## 1. Objetivos

- Gerenciar vários condomínios em uma única aplicação.
- Isolar dados, operações e permissões entre condomínios.
- Permitir que uma pessoa tenha acesso a vários condomínios e papéis distintos em cada contexto.
- Exigir conta de acesso para todo morador cadastrado.
- Cadastrar funcionários próprios e terceirizados.
- Permitir que uma prestadora atenda vários condomínios e que um condomínio contrate várias prestadoras.
- Manter histórico e rastreabilidade de operações administrativas e financeiras.

## 2. Princípios arquiteturais

### 2.1 Multi-tenancy

O condomínio é o contexto de isolamento operacional. Recomenda-se começar com banco compartilhado e `condominium_id` nas entidades que pertencem diretamente a um condomínio.

Regras:
1. Não confiar em `condominium_id` enviado pelo cliente sem validar o vínculo do usuário.
2. Resolver o condomínio ativo por middleware/contexto autenticado.
3. Aplicar autorização por Policies/Gates.
4. Usar scopes do Eloquent como conveniência, nunca como única barreira de segurança.
5. Reforçar integridade com FKs e, quando necessário, chaves únicas compostas.
6. Testar tentativas de leitura e alteração entre condomínios.
7. Executar Jobs e comandos com contexto de condomínio explicitamente definido.

### 2.2 Identidade, usuários e papéis

- `users`: conta de autenticação.
- `roles` e `permissions`: autorização.
- `condominium_users`: associação do usuário ao condomínio e papel naquele contexto.
- Um usuário pode ter papéis diferentes em condomínios diferentes.
- Papéis globais de suporte da plataforma, se existirem, devem ser separados dos papéis operacionais.

Recomendação: Laravel Policies/Gates e, se adequado, Spatie Laravel Permission. Evitar um único `role_id` em `users`, pois os papéis são contextuais.

### 2.3 Moradores e contas

Todo morador cadastrado deve possuir uma conta em `users`. `residents` guarda dados específicos da condição de morador; `unit_residents` registra o vínculo temporal com uma unidade.

O vínculo morador-unidade é N:N ao longo do tempo e deve guardar tipo de vínculo, início, fim e situação.

**Pendente:** confirmar se uma pessoa pode morar em várias unidades simultaneamente e os tipos de vínculo aceitos (proprietário, coproprietário, inquilino, dependente, ocupante etc.).

### 2.4 Estrutura física

Hierarquia: `condominiums -> blocks (opcional) -> units`.

- `blocks` representa torre, bloco ou setor.
- `units` representa apartamento, casa, sala ou unidade autônoma.
- O bloco é opcional para suportar condomínios horizontais.
- `units` mantém `condominium_id`; quando houver `block_id`, deve-se garantir que o bloco pertence ao mesmo condomínio.

### 2.5 Empresas e funcionários

- `companies` é o cadastro único da prestadora, independente de um condomínio.
- `company_condominiums` representa o vínculo/contrato entre prestadora e condomínio.
- Empresa pode atender vários condomínios; condomínio pode contratar várias empresas.
- `employees` registra funcionários próprios ou terceirizados.
- Funcionário próprio: `condominium_id` preenchido e `company_id` nulo.
- Funcionário terceirizado: `company_id` preenchido e `condominium_id` nulo no cadastro global. A autorização para atuar em condomínio decorre do vínculo da empresa e de uma concessão de acesso específica, se houver login.

Não duplicar o cadastro da empresa para cada condomínio.

**Pendente:** definir se funcionários terceirizados terão login e se poderão acessar vários condomínios atendidos pela empresa. O acesso deve ser explícito, não concedido automaticamente a todos os condomínios da prestadora.

### 2.6 Administração, reservas e financeiro

Separar conceitos:
- Ocorrência: fato/reclamação registrada.
- Multa: penalidade formal, com fundamento, valor, situação e eventual contestação.
- Advertência: medida administrativa sem cobrança obrigatória.
- Reserva: solicitação de uso de área comum.
- Ordem de serviço: solicitação e acompanhamento de serviço.
- Lançamento financeiro: obrigação, receita ou despesa.
- Pagamento: liquidação total ou parcial de lançamento.

Uma ocorrência pode originar multa ou advertência, mas não precisa gerar penalidade.

## 3. Organização sugerida no Laravel

```text
app/
├── Domains/
│   ├── Access/
│   ├── Condominium/
│   ├── Structure/
│   ├── Residents/
│   ├── Companies/
│   ├── Employees/
│   ├── Administration/
│   ├── Reservations/
│   ├── ServiceOrders/
│   └── Finance/
├── Jobs/
├── Events/
├── Listeners/
├── Notifications/
└── Support/
    └── Tenancy/
```

Diretrizes:
- Controllers coordenam HTTP, validação de entrada e resposta.
- Services/Actions implementam casos de uso com responsabilidades claras.
- Form Requests validam entrada.
- Policies autorizam operações.
- Events/Listeners desacoplam reações a fatos de negócio.
- Jobs/Queues processam tarefas demoradas, como e-mails e notificações.
- Para os módulos do backend, seguir Controller → Service → Repository: consultas e persistência de aplicação ficam nos repositórios; regras de negócio e transações ficam nos services. Controllers tratam HTTP, validação e autorização. O Laravel continua responsável pelo binding de modelos, validações `exists`/`unique` e mecanismos de autenticação.

## 4. Módulos e entidades

| Módulo | Responsabilidade | Entidades |
|---|---|---|
| Acesso | Autenticação e autorização | users, roles, permissions, condominium_users |
| Condomínios | Cadastro e configuração | condominiums |
| Estrutura | Blocos e unidades | blocks, units |
| Moradores | Pessoas e ocupação | residents, unit_residents |
| Empresas | Prestadoras e contratos | companies, company_condominiums |
| Funcionários | Equipes próprias e terceirizadas | employees |
| Administração | Ocorrências e penalidades | occurrences, fines, warnings |
| Reservas | Áreas comuns e agendamentos | common_areas, reservations |
| Serviços | Solicitações e execução | service_orders |
| Financeiro | Categorias, lançamentos e liquidações | financial_categories, financial_transactions, financial_payments |
| Auditoria | Histórico de ações | audit_logs |

## 5. Integridade, desempenho e dados

- Preferir `restrictOnDelete` para dados históricos/financeiros; usar exclusão lógica quando necessário.
- Usar `cascadeOnDelete` apenas em dependências sem valor histórico próprio.
- Criar índices para `condominium_id`, status, datas e chaves de busca.
- Definir unicidade da unidade conforme regra: número único no condomínio ou dentro do bloco.
- Garantir que FKs relacionadas pertençam ao mesmo tenant, com constraints compostas quando viável ou validação transacional rigorosa.
- Nunca usar `float` para dinheiro; usar `DECIMAL` ou inteiros em centavos.
- Padronizar timezone de armazenamento e apresentação.
- Registrar autor, data e motivo para alterações sensíveis.

## 6. Segurança

| Papel | Escopo esperado |
|---|---|
| Administrador da plataforma | Operações globais estritamente administrativas |
| Síndico | Condomínio(s) em que possui vínculo |
| Funcionário do condomínio | Funcionalidades liberadas para sua função |
| Morador | Seus dados, unidade(s), reservas e informações autorizadas |
| Funcionário terceirizado | Apenas ordens/serviços e dados necessários aos contratos autorizados |

A autorização deve considerar usuário + condomínio + ação + recurso. Não presumir acesso global pelo nome do papel.

## 7. Testes mínimos

1. Usuário do Condomínio A não consulta/altera registros do Condomínio B.
2. Usuário sem vínculo ativo não acessa o condomínio.
3. Papéis distintos por condomínio resultam em permissões corretas.
4. Morador não pode existir sem conta válida.
5. Vínculos de moradores respeitam vigência e regras de ocupação.
6. Prestadora atende vários condomínios sem duplicação cadastral.
7. Ordem de serviço só usa vínculo empresa-condomínio válido.
8. Funcionários próprios e terceirizados respeitam escopos.
9. Reservas concorrentes não ocupam a mesma área em horários conflitantes.
10. Pagamentos parciais/totais atualizam corretamente o saldo.
11. Jobs e notificações respeitam o contexto do condomínio.

## 8. Decisões pendentes

- Se administradoras serão entidades próprias e poderão gerenciar vários condomínios.
- Se uma unidade pode ter vários proprietários e um proprietário várias unidades.
- Se proprietário e inquilino podem estar ativos simultaneamente.
- Se o financeiro é independente por condomínio ou centralizado pela administradora.
- Se funcionário terceirizado terá login e como receberá acesso.
- Se blocos são opcionais (recomendado: sim).
- Como identificar unidades unicamente.
- Regras de multas, juros, inadimplência, rateios e contas bancárias.

## 9. Roadmap

1. Identidade, autenticação e autorização contextual.
2. Condomínios e isolamento multi-tenant.
3. Blocos, unidades e moradores.
4. Empresas, contratos e funcionários.
5. Ocorrências, multas e advertências.
6. Áreas comuns e reservas.
7. Ordens de serviço.
8. Categorias, lançamentos e pagamentos.
9. Auditoria, notificações e relatórios.

## 10. Migrations do diagrama

As migrations implementam as entidades de `database-erd.mmd` em ordem de dependência e reutilizam a migration existente de `users`. Valores monetários usam `decimal(15, 2)`; os registros possuem também `created_at` e `updated_at`. As referências de unidade, bloco, categoria financeira e contrato de prestadora verificam o condomínio correspondente.

Os campos sem obrigatoriedade indicada no diagrama foram considerados obrigatórios, exceto os vínculos opcionais e os fins de vigência; `contract_reference` também é opcional. `reported_by` e `requested_by` referenciam `users`. As migrations não implementam autorização, validação de contratos ativos, conflitos de reservas nem outras regras de negócio pendentes.

Para aplicar em PostgreSQL, configure a conexão `pgsql` no ambiente e habilite a extensão PHP `pdo_pgsql`. Revise as migrations pendentes com `php artisan migrate:status` e, se apropriado, execute `php artisan migrate`. Em produção, faça backup antes e siga o procedimento de implantação do ambiente.

As models Eloquent correspondem às tabelas do diagrama. Os vínculos `condominium_users`, `unit_residents` e `company_condominiums` são models próprios para preservar seus atributos e histórico. Os relacionamentos não substituem a autorização por condomínio, que deve ser aplicada pela aplicação.

## 11. Autenticação do frontend

O frontend usa autenticação de SPA do Sanctum com sessão Laravel e CSRF, encaminhando as requisições ao backend pela mesma origem. As rotas de sessão e identidade estão em `routes/api.php`.

Configure uma `APP_KEY` válida, a sessão e `SANCTUM_STATEFUL_DOMAINS` com os domínios e portas do frontend; em produção use HTTPS e cookies seguros. A migration publicada pelo Sanctum deve ser aplicada juntamente com as demais migrations. A autenticação não concede autorização por papel ou condomínio: policies e isolamento multi-tenant ainda precisam ser implementados antes de disponibilizar dados de negócio.

## 12. Identidade e acessos

Execute as migrations pendentes e configure `FRONTEND_URL` e um serviço de e-mail funcional no backend. Inicialize o administrador `admin@admin.com.br` com `php artisan access:initialize-admin`; a senha é solicitada sem exibição e não é cadastrada automaticamente. Caso a conta já exista, use `php artisan access:initialize-admin --promote-existing` para promovê-la explicitamente. Não compartilhe a senha.

Somente a conta inicializada como administradora pode convidar usuários, cadastrar perfis e permissões, atribuir permissões a perfis e conceder ou encerrar vínculos por condomínio. Convites e recuperação usam links de senha de uso único; definir a senha confirma o e-mail. Usuários não verificados também podem solicitar verificação pelo perfil. A concessão de acesso gera uma notificação interna.

As permissões cadastradas descrevem os acessos atribuídos; os módulos de negócio ainda devem aplicar essas permissões com policies e validar o condomínio ativo no servidor. A interface não é uma barreira de autorização. A entrega de e-mails depende da configuração do provedor; o driver `log` não entrega mensagens reais.

## 13. Cadastro de condomínios

O administrador da plataforma cadastra, inativa e reativa condomínios. Usuários com vínculo vigente e a permissão `condominios.editar` podem consultar e editar somente seus condomínios ativos; o administrador deve cadastrar e atribuir essa permissão na tela de acessos. A inativação preserva os registros e suspende os vínculos enquanto durar; não é possível conceder novos acessos ao condomínio inativo. Outros módulos futuros também deverão verificar a situação e o vínculo no servidor.

Nome e CNPJ são obrigatórios. CNPJs numéricos e alfanuméricos são validados, normalizados sem pontuação e armazenados em maiúsculas; o banco impede duplicidades. Antes de aplicar `2026_11_13_160000_unique_condominium_document`, regularize documentos existentes inválidos, formatados, em minúsculas ou duplicados e situações diferentes de `active` ou `inactive`. A migration interrompe a execução e informa o primeiro ID que precisa de correção, sem alterar os dados existentes. Faça backup e revise os dados antes de migrar.

As rotas autenticadas `/api/condominiums` permitem listagem, criação, consulta e edição; `PATCH /api/condominiums/{id}/status` altera a situação apenas para o administrador.
O controller valida as requisições HTTP, o service concentra as regras e transações e o repository acessa os dados. A policy garante a autorização contextual.

senha: password123456
