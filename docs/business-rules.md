# Regras de Negócio — Sistema de Gerenciamento de Condomínios

**Status:** baseline inicial. Regras marcadas como pendentes precisam ser decididas antes da implementação definitiva.

Legenda:
- **Obrigatória:** decisão já informada no escopo.
- **Proposta:** recomendação para validação.
- **Pendente:** depende de decisão do produto.

## 1. Plataforma e condomínios

### RN-001 — Múltiplos condomínios
**Obrigatória.** Uma instalação gerencia vários condomínios.

### RN-002 — Isolamento
**Obrigatória.** Usuário só consulta ou altera dados de condomínio em que possui vínculo e autorização ativos.

### RN-003 — Contexto ativo
**Proposta.** Operações de domínio devem executar em contexto explícito de condomínio. O backend valida o vínculo; não confia apenas no ID enviado pelo frontend.

### RN-004 — Administradora
**Pendente.** Definir se administradoras serão entidades próprias, se gerenciam vários condomínios e como ocorre troca/encerramento do vínculo.

## 2. Usuários, papéis e permissões

### RN-010 — Conta de usuário
Cada pessoa que acessa a plataforma possui uma conta em `users`, com política de e-mail único.

### RN-011 — Papéis por condomínio
**Proposta.** Um usuário pode ter papéis diferentes em condomínios diferentes. O papel é associado ao vínculo usuário-condomínio.

### RN-012 — Múltiplos papéis
**Proposta.** Uma pessoa pode acumular papéis no mesmo condomínio quando autorizado. As permissões efetivas não podem exceder o necessário.

### RN-013 — E-mail e notificações
**Proposta.** Convites, verificação, redefinição de senha e comunicados usam Notifications/Jobs. Falhas devem ser registradas e permitir nova tentativa segura.

## 3. Moradores e unidades

### RN-020 — Conta obrigatória
**Obrigatória.** Todo morador cadastrado deve estar associado a uma conta `users`. Recomenda-se `residents.user_id` obrigatório e único se cada registro representar uma pessoa.

O cadastro de moradores exige unidade e conta existentes. Administradores locais só podem selecionar contas com vínculo ativo no condomínio; o administrador geral pode selecionar qualquer conta. Os dados pessoais são compartilhados entre vínculos do mesmo morador.

O administrador geral e usuários com vínculo vigente e perfil `Admin`, `Administrador`, `Síndico` ou `Sindico` gerenciam moradores no condomínio ativo. A exclusão remove o cadastro de morador e seu único vínculo, mas preserva a conta; é impedida quando há outros vínculos, ocorrências ou reservas. Dados pessoais associados a outro condomínio só podem ser alterados pelo administrador geral.

### RN-021 — Vínculo temporal
**Proposta.** `unit_residents` registra início, fim, tipo e situação. Encerrar vínculo não apaga histórico.

### RN-022 — Vários moradores por unidade
**Proposta.** Uma unidade pode ter vários moradores simultaneamente.

### RN-023 — Um morador em várias unidades
**Pendente.** Confirmar se pode haver vínculo simultâneo com mais de uma unidade.

### RN-024 — Tipos de vínculo
**Pendente.** Definir tipos: proprietário, coproprietário, inquilino, dependente, ocupante etc. Tipo de vínculo não equivale automaticamente a papel de acesso.

### RN-025 — Proprietário e inquilino
**Pendente.** Confirmar coexistência e permissões de cada vínculo.

### RN-026 — Estrutura
**Proposta.** Bloco/torre é opcional; unidade pertence diretamente ao condomínio e pode pertencer a um bloco.

### RN-027 — Identificação da unidade
**Pendente.** Definir se número é único no condomínio ou apenas dentro do bloco.

## 4. Empresas e funcionários

### RN-030 — Cadastro único de prestadora
**Obrigatória.** Empresa prestadora é cadastrada uma única vez, mesmo que atenda vários condomínios.

### RN-031 — Relação empresa-condomínio
**Obrigatória.** Relação N:N: uma empresa atende vários condomínios e um condomínio contrata várias empresas. Usar `company_condominiums`.

### RN-032 — Contrato por condomínio
**Proposta.** O vínculo registra situação, início e fim da vigência. Encerrar contrato não apaga empresa nem histórico de serviços.

### RN-033 — Funcionário próprio
**Obrigatória.** Condomínio pode cadastrar funcionário contratado diretamente, sem empresa terceirizada.

### RN-034 — Funcionário terceirizado
**Obrigatória.** Funcionário terceirizado pertence a uma empresa. Acesso ao condomínio depende do vínculo da empresa e de autorização explícita.

### RN-035 — Login de funcionário
**Pendente.** Definir quais funcionários terão login e se terceirizados podem acessar vários condomínios atendidos pela empresa.

### RN-036 — Tipo de empresa
**Proposta.** Categorias como limpeza, segurança, jardinagem e manutenção podem ser classificações cadastradas; permitir múltiplas categorias se necessário.

## 5. Ocorrências, multas e advertências

### RN-040 — Ocorrência
**Proposta.** Registra fato, descrição, data, condomínio, autor e, quando aplicável, unidade e pessoas envolvidas.

### RN-041 — Penalidades
**Proposta.** Multa e advertência são registros próprios derivados de ocorrência. Uma ocorrência pode não gerar penalidade.

### RN-042 — Multa
**Pendente.** Definir fundamento/regulamento, valor, vencimento, destinatário, status, contestação, aprovação e reincidência.

### RN-043 — Advertência
**Pendente.** Definir etapas, prazos, resposta e eventual conversão em multa.

### RN-044 — Integração financeira
**Proposta.** Multa aprovada pode originar lançamento financeiro. Reprocessamento não pode duplicar a cobrança.

## 6. Áreas comuns e reservas

### RN-050 — Áreas comuns
**Proposta.** Cada área pertence a um condomínio e possui regras de disponibilidade, capacidade, antecedência, duração, preço e aprovação.

### RN-051 — Conflito de reservas
**Proposta.** Reservas que bloqueiam agenda não podem se sobrepor para a mesma área. A validação deve ser transacional.

### RN-052 — Cancelamento
**Pendente.** Definir prazos, multas, reembolsos e status que bloqueiam agenda.

### RN-053 — Cobrança de reserva
**Pendente.** Definir se há cobrança, quando é criada e como se relaciona ao financeiro.

## 7. Serviços e ordens de serviço

### RN-060 — Ordem de serviço
**Proposta.** Pertence a um condomínio e registra descrição, prioridade, datas, status, solicitante e executor.

### RN-061 — Prestadora autorizada
**Obrigatória como regra de integridade.** Ordem atribuída a empresa deve referenciar vínculo empresa-condomínio válido para o condomínio da ordem.

### RN-062 — Histórico
**Proposta.** Mudanças de status, atribuições, comentários e anexos mantêm histórico e autoria.

## 8. Financeiro

### RN-070 — Separação financeira
**Proposta.** Lançamentos pertencem a um condomínio; saldos de condomínios diferentes não são misturados.

### RN-071 — Categorias
**Proposta.** Categorias distinguem receitas e despesas e pertencem ao condomínio, salvo categorias globais padrão.

### RN-072 — Lançamentos e pagamentos
**Proposta.** Lançamento representa valor devido/recebível ou despesa; pagamento representa liquidação parcial ou total.

### RN-073 — Valores monetários
**Diretriz técnica obrigatória.** Não usar ponto flutuante. Usar DECIMAL ou inteiros em centavos.

### RN-074 — Inadimplência
**Pendente.** Definir juros, multa moratória, correção, carência, renegociação, baixa e estorno.

### RN-075 — Contas bancárias
**Pendente.** Definir contas próprias por condomínio e eventual visão consolidada da administradora, sem misturar titularidade de recursos.

## 9. Auditoria e segurança

### RN-080 — Auditoria
**Proposta.** Registrar autor, data/hora, ação, entidade afetada e alterações sensíveis.

### RN-081 — Exclusão
**Proposta.** Dados financeiros, contratos e registros administrativos não devem ser apagados fisicamente quando precisarem de histórico. Preferir cancelamento/encerramento ou exclusão lógica.

### RN-082 — Menor privilégio
**Obrigatória como princípio.** Usuário recebe somente permissões necessárias às funções e condomínios em que atua.

### RN-083 — LGPD
**Proposta.** Definir finalidade, retenção, acesso, correção e descarte de dados pessoais.

## 10. Perguntas pendentes

1. Administradoras serão entidades próprias? Uma administradora pode gerenciar vários condomínios? Resposta: Sim!
2. Uma unidade pode ter vários proprietários? Um proprietário pode possuir várias unidades? Resposta: Sim!
3. Proprietário e inquilino podem estar ativos simultaneamente? Resposta: Sim!
4. Financeiro independente por condomínio ou centralizado pela administradora? Resposta: Cada condominio tem um financeiro!
5. Funcionários terceirizados terão login? Poderão acessar vários condomínios? Reposta: Apenas funcionário do condominio, terceirizado não!
6. Blocos/torres serão opcionais? Resposta: Sim, pois cada condominio determina se vai ser bloco, torre ou qualquer outro nome.
7. Número da unidade é único no condomínio ou dentro do bloco? Resposta: É unico no bloco.
8. Como calcular e contestar multas, juros e inadimplência? Resposya: cada condominio vai determinar a sua regra de juros.
9. Reservas exigem pagamento ou aprovação?
10. Quais status serão usados em ocorrências, ordens, contratos e pagamentos? Resposta: pode ser separado em reclamação, denuncia, problemas técnicos

## 11. Critérios de aceite da primeira versão

- Cadastrar vários condomínios na mesma instalação.
- Impedir acesso cruzado entre condomínios.
- Exigir conta de usuário para todo morador.
- Permitir papéis diferentes por condomínio.
- Permitir condomínio com ou sem blocos.
- Permitir vários moradores por unidade, conforme regras de vigência.
- Cadastrar prestadora uma vez e vinculá-la a vários condomínios.
- Permitir várias prestadoras por condomínio.
- Diferenciar funcionários próprios e terceirizados.
- Impedir ordem de serviço com prestadora sem vínculo autorizado.
- Cobrir isolamento multi-tenant com testes automatizados.
