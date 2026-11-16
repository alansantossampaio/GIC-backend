# 📊 Fluxo de Convites - Diagrama Visual

## Comparação: Antes vs Depois

### ❌ ANTES (Erro)

```
usuario@exemplo.com
        ↓
   [POST /invite]
        ↓
   Validar unique
        ↓
   Email já existe?
        ↓
   🔴 ERRO: "Email already taken"
```

**Resultado**: Impossível re-convidar um usuário que não confirmou

---

### ✅ DEPOIS (Funcionando)

```
usuario@exemplo.com
        ↓
   [POST /invite]
        ↓
   Validar email (formato)
        ↓
   Buscar usuario
        ↓
   Existe? ──NÃO──→ Criar novo usuário
        │
        SIM
        │
        ├─→ Verificado? ──SIM──→ 🔴 ERRO: "Já confirmou"
        │                  └─ Campo: email_verified_at != null
        │
        └─→ NÃO VERIFICADO
                   ↓
              Atualizar nome
                   ↓
              Enviar novo link
                   ↓
              ✅ "Convite enviado"
              (is_reinvite: true)
```

---

## 🔄 Estados do Usuário

### Estado 1: Novo (Não existe)
```
Usuário: não existe na tabela
Ação: Criar novo
Email_verified_at: NULL
Resposta: is_reinvite = false
```

### Estado 2: Convidado (Existe, não confirmou)
```
Usuário: existe na tabela
Email_verified_at: NULL
Ação: Re-convidar (reutilizar, atualizar nome, novo link)
Resposta: is_reinvite = true
```

### Estado 3: Ativo (Existe, confirmou)
```
Usuário: existe na tabela
Email_verified_at: 2026-11-13 15:00:00
Ação: ❌ REJEITAR
Resposta: ERRO 422
```

---

## 📱 Uso no Frontend

### Exemplo de Formulário

```typescript
async function handleInviteUser(email: string, name: string) {
  try {
    const response = await fetch('/api/access-management/invite', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, name })
    });

    if (!response.ok) {
      const error = await response.json();
      if (error.errors?.email?.[0]) {
        // Mostrar erro específico
        showErrorMessage(error.errors.email[0]);
        // Ex: "Este usuário já confirmou sua conta."
      }
      return;
    }

    const data = await response.json();
    
    // Mostrar mensagem diferente para re-convites
    if (data.is_reinvite) {
      toast.info(`Novo convite enviado para ${email}`);
    } else {
      toast.success(`Usuário ${name} convidado com sucesso`);
    }
    
  } catch (error) {
    toast.error('Erro ao enviar convite');
  }
}
```

---

## 📋 Tabela de Respostas

| Caso | HTTP | Mensagem | is_reinvite |
|------|------|----------|------------|
| Email novo | 201 | "Convite enviado." | `false` |
| Re-convite | 201 | "Convite enviado." | `true` |
| Usuário confirmado | 422 | "Já confirmou sua conta" | N/A |

---

## 🔐 Segurança Implementada

### 1. Transação Atômica
- Toda operação (buscar, criar/atualizar, enviar email) é atômica
- Se falhar em qualquer passo, tudo é revertido

### 2. Validação em Camadas
```
Frontend: Validação HTML5
  ↓
Backend: Validação Laravel
  ↓
Database: Foreign keys + constraints
```

### 3. Proteção contra Re-usar Conta Confirmada
- Código verifica `email_verified_at !== null`
- Impossível forçar re-convite de conta ativa

### 4. Senha Aleatória
- Usuário recebe link único por email
- Senha gerada no servidor (64 caracteres aleatórios)
- Usuário define própria senha no link

---

## 🧪 Testes Manuais

### Teste 1: Primeiro Convite
```bash
curl -X POST http://localhost:8000/api/access-management/invite \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Maria Silva",
    "email": "maria@exemplo.com"
  }'

# Esperado:
# HTTP 201
# {
#   "id": 5,
#   "message": "Convite enviado.",
#   "is_reinvite": false
# }
```

### Teste 2: Re-convite (não confirmado)
```bash
curl -X POST http://localhost:8000/api/access-management/invite \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Maria Silva",
    "email": "maria@exemplo.com"
  }'

# Esperado:
# HTTP 201
# {
#   "id": 5,
#   "message": "Convite enviado.",
#   "is_reinvite": true  # ← Flag diferente!
# }
```

### Teste 3: Usuário Confirmado
```bash
# Primeiro, abrir link e definir senha em: 
# http://localhost:3000/auth/redefinir-senha?token=...&email=maria@exemplo.com

# Depois, tentar convidar novamente:
curl -X POST http://localhost:8000/api/access-management/invite \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Maria Silva",
    "email": "maria@exemplo.com"
  }'

# Esperado:
# HTTP 422
# {
#   "message": "The given data was invalid.",
#   "errors": {
#     "email": ["Este usuário já confirmou sua conta."]
#   }
# }
```

---

## 🎯 Checklist de Validação

- [ ] Testar primeiro convite com novo email
- [ ] Verificar se email chega com link correto
- [ ] Testar re-convite sem abrir primeiro link
- [ ] Verificar se novo link é diferente do anterior
- [ ] Abrir link de convite e definir senha
- [ ] Tentar convidar novamente (deve dar erro)
- [ ] Verificar mensagens no frontend
- [ ] Verificar logs no `storage/logs/laravel.log`

---

**Status**: ✅ **IMPLEMENTADO**  
**Data**: 13/11/2026
