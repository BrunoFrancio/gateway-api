# API de Gateways

Sistema para gerenciamento de gateways com suporte a enfileiramento de SQLs cifrados para trânsito seguro e execução por agente local.

## Índice

- [Autenticação e Headers](#autenticação-e-headers)
- [API de Gateways](#endpoints-gateways)
  - [Listar Gateways](#listar-gateways)
  - [Criar Gateway](#criar-gateway)
  - [Detalhar Gateway](#detalhar-gateway)
  - [Atualizar Gateway](#atualizar-gateway)
  - [Rotacionar Chave](#rotacionar-chave)
- [Jobs de SQL por Gateway](#jobs-de-sql-por-gateway)
  - [Criar Job de SQL](#criar-job-de-sql-admin)
  - [Listar Jobs Pendentes](#listar-jobs-pendentes-agente)
  - [Confirmar Processamento (ACK)](#confirmar-processamento-ack)
  - [Registrar Falha](#registrar-falha)
- [Criptografia em Trânsito](#criptografia-em-trânsito)
- [Auditoria & Logs](#auditoria--logs)
- [Troubleshooting](#troubleshooting-rápido)

---

## Autenticação e Headers

**Auth:** HTTP Basic (usuário/senha da tabela users)

**Headers obrigatórios em chamadas JSON:**
- `Accept: application/json`
- `Content-Type: application/json` (quando houver corpo)

**Exemplo de header + auth:**

```bash
curl -u admin@local.test:admin12345 \
  -H "Accept: application/json" \
  http://localhost:8080/api/gateways
```

> **Nota de produção:** em ambientes públicos, recomenda-se restringir endpoints "admin-only" via Gate/Policy ou outro mecanismo.

---

## Endpoints Gateways

### Listar Gateways

**GET** `/api/gateways?active=&search=&per_page=`

**Parâmetros:**
- `active`: 1 (true) ou 0 (false) — opcional
- `search`: termo livre (nome) — opcional
- `per_page`: padrão 15 — opcional

**Exemplo:**

```bash
curl -u admin@local.test:admin12345 -s \
  -H "Accept: application/json" \
  "http://localhost:8080/api/gateways?active=1&search=GW&per_page=10"
```

**Resposta (exemplo):**

```json
{
  "data": [
    {
      "id": "01k6p5ezwvnj2j9zjazztp48gz",
      "nome": "GW Local 02",
      "ativo": true,
      "key_id": "key_v1",
      "key_alg": "app-key@laravel-crypt",
      "key_rotated_at": null,
      "last_seen_at": null,
      "observacoes": null,
      "criado_por": 1,
      "atualizado_por": null,
      "created_at": "2025-10-03T23:10:39.000000Z",
      "updated_at": "2025-10-03T23:10:39.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1
  },
  "links": {
    "first": "http://localhost:8080/api/gateways?page=1",
    "last": "http://localhost:8080/api/gateways?page=1",
    "prev": null,
    "next": null
  }
}
```

---

### Criar Gateway

**POST** `/api/gateways`

**Corpo:**

```json
{
  "nome": "GW API 01",
  "ativo": true,
  "observacoes": "criado via API"
}
```

**Exemplo:**

```bash
curl -u admin@local.test:admin12345 -s \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8080/api/gateways \
  -d '{"nome":"GW API 01","ativo":true,"observacoes":"criado via API"}'
```

**Resposta (exemplo):**

```json
{
  "data": {
    "id": "01k6p9p7re7q9e83daaqv1jpyp",
    "nome": "GW API 01",
    "ativo": true,
    "key_id": "key_v1",
    "key_alg": "app-key@laravel-crypt",
    "key_rotated_at": null,
    "last_seen_at": null,
    "observacoes": "criado via API",
    "criado_por": 1,
    "atualizado_por": null,
    "created_at": "2025-10-04T00:24:31.000000Z",
    "updated_at": "2025-10-04T00:24:31.000000Z"
  }
}
```

---

### Detalhar Gateway

**GET** `/api/gateways/{id}`

**Exemplo:**

```bash
curl -u admin@local.test:admin12345 -s \
  -H "Accept: application/json" \
  http://localhost:8080/api/gateways/01k6p9p7re7q9e83daaqv1jpyp
```

---

### Atualizar Gateway

**PATCH** `/api/gateways/{id}`

**Corpo (exemplo):**

```json
{
  "nome": "GW API 01 (editado)",
  "ativo": true,
  "observacoes": "ajuste de nome"
}
```

**Exemplo:**

```bash
curl -u admin@local.test:admin12345 -s \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -X PATCH http://localhost:8080/api/gateways/01k6p9p7re7q9e83daaqv1jpyp \
  -d '{"nome":"GW API 01 (editado)","ativo":true,"observacoes":"ajuste de nome"}'
```

---

### Rotacionar Chave

**PATCH** `/api/gateways/{id}/rotate`

Não há corpo. Gera `key_v{n+1}`, atualiza `key_rotated_at` e audita a ação.

**Exemplo:**

```bash
curl -u admin@local.test:admin12345 -s \
  -H "Accept: application/json" \
  -X PATCH http://localhost:8080/api/gateways/01k6p9p7re7q9e83daaqv1jpyp/rotate
```

**Resposta (exemplo):**

```json
{
  "mensagem": "Chave rotacionada com sucesso.",
  "data": {
    "id": "01k6p9p7re7q9e83daaqv1jpyp",
    "nome": "GW API 01 (editado)",
    "key_id": "key_v2",
    "key_alg": "app-key@laravel-crypt",
    "key_rotated_at": "2025-10-04T00:30:36.000000Z",
    "atualizado_por": 1,
    "updated_at": "2025-10-04T00:30:36.000000Z"
  }
}
```

---

## Jobs de SQL por Gateway

Permite enfileirar SQLs cifrados para trânsito por Gateway e consumi-los pelo agente local.

### Criar Job de SQL (admin)

**POST** `/api/gateways/{id}/sql-jobs`

**Headers:** `Accept: application/json`, `Content-Type: application/json`  
**Auth:** Basic (usuário admin)

**Body:**

```json
{
  "sql": "UPDATE contas SET ativo = true WHERE id = 123;",
  "disponivel_em": null
}
```

**Exemplo:**

```bash
ID=$(curl -s -u admin@local.test:admin12345 -H "Accept: application/json" \
  http://localhost:8080/api/gateways \
  | python3 -c 'import sys,json; print(json.load(sys.stdin)["data"][0]["id"])')

curl -s -u admin@local.test:admin12345 \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -X POST "http://localhost:8080/api/gateways/$ID/sql-jobs" \
  -d '{"sql":"UPDATE contas SET ativo = true WHERE id = 123;"}'
```

**Resposta (exemplo):**

```json
{
  "data": {
    "id": "01k6qnta4nqnxqa79nb1j7thbg",
    "gateway_id": "01k6kjhj1p03y2x41yj4pqsks3",
    "status": "pending",
    "transit_alg": "xchacha20poly1305@libsodium",
    "key_id": "key_v3",
    "disponivel_em": null,
    "created_at": "2025-10-04T13:15:42.000000Z"
  }
}
```

---

### Listar Jobs Pendentes (agente)

**GET** `/api/gateways/{id}/sql-jobs/pending`

**Headers:** `Accept: application/json`  
**Auth:** Basic (temporário)

> **Observação:** autenticação dedicada do agente está no backlog.

**Resposta (exemplo):**

```json
{
  "data": [
    {
      "id": "01k6qnta4nqnxqa79nb1j7thbg",
      "gateway_id": "01k6kjhj1p03y2x41yj4pqsks3",
      "key_id": "key_v3",
      "transit_alg": "xchacha20poly1305@libsodium",
      "sql_ciphertext": "<base64>",
      "nonce": "<base64>",
      "tag": null,
      "tentativas": 0,
      "status": "pending",
      "disponivel_em": null,
      "created_at": "2025-10-04T13:15:42.000000Z"
    }
  ]
}
```

---

### Confirmar Processamento (ACK)

**POST** `/api/gateways/{id}/sql-jobs/{job}/ack`

**Headers:** `Accept: application/json`  
**Auth:** Basic (temporário)

**Resposta (exemplo):**

```json
{
  "mensagem": "ack registrado"
}
```

---

### Registrar Falha

**POST** `/api/gateways/{id}/sql-jobs/{job}/fail`

**Headers:** `Accept: application/json`, `Content-Type: application/json`  
**Auth:** Basic (temporário)

**Body:**

```json
{
  "mensagem": "erro de sintaxe no SQL"
}
```

**Resposta (exemplo):**

```json
{
  "mensagem": "falha registrada"
}
```

---

## Criptografia em Trânsito

### Preferência: libsodium

**Algoritmo:** `xchacha20poly1305@libsodium`

**Campos:**
- `sql_ciphertext` (base64)
- `nonce` (base64)
- `tag` = null (MAC já embutido no ciphertext)

**Decifrar (PHP):**

```php
sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, '', $nonce, $key)
```

### Fallback: OpenSSL

**Algoritmo:** `aes-256-gcm@openssl`

**Campos:**
- `sql_ciphertext` (base64)
- `nonce` (IV, base64)
- `tag` (base64)

**Decifrar (PHP):**

```php
openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag)
```

### Observações de Segurança

- A chave usada é o material do próprio Gateway (campo `key_id` indica a versão ativa)
- O agente deve decifrar com a mesma `key_id`
- **Dependências:** ext-sodium (recomendado) ou ext-openssl
- **Segurança:** o SQL nunca é logado em texto puro; somente ciphertext e metadados
- O material de chave não é exposto nos logs
- Em trânsito, os dados vão cifrados
- Em repouso, o projeto mantém o campo `key_id` e o material da chave para uso do agente
- Cifrar em repouso via `APP_KEY` pode ser habilitado/ajustado conforme política do ambiente

---

## Auditoria & Logs

### Canais de Log

**SQL Jobs:**
- Canal: `gateway_sql`
- Arquivos: `storage/logs/gateway_sql-*.log`
- Eventos: `sql_job_criado`, `sql_job_enviado`, `sql_job_ack`, `sql_job_falha`

**Chaves/Gateways:**
- Canal: `gateway_audit` (se habilitado)
- Arquivos: `storage/logs/gateway_audit-*.log`

### Ver Últimos Logs

```bash
# Listar logs disponíveis
docker compose exec app sh -lc "ls -1 storage/logs | grep -E 'gateway_(sql|audit)' || true"

# Ver últimos registros de SQL jobs
docker compose exec app sh -lc "tail -n 50 storage/logs/gateway_sql-*.log"

# Ver últimos registros de auditoria
docker compose exec app sh -lc "tail -n 50 storage/logs/gateway_audit-*.log"
```

> **Importante:** o material da chave permanece cifrado em repouso e não é logado em texto puro.

---

## Troubleshooting Rápido

### Invalid route action …

Conferir namespace/classe das Actions e rodar:

```bash
docker compose exec app composer dump-autoload
docker compose exec app php artisan optimize:clear
```

### 404 /api/…

Checar `routes/api.php` e cache de rotas.

### 401/403

Verificar credenciais Basic Auth. Endpoints são "admin-only".

### The payload is invalid. (DecryptException)

Verificar:
- Compatibilidade entre armazenamento do material da chave e o serviço de cifra em trânsito
- Garantir que o agente usa `key_id` e algoritmo corretos ao decifrar
- Confirmar que a extensão correta está disponível (ext-sodium ou ext-openssl)

### Sem libsodium

Se a extensão `ext-sodium` não estiver disponível, o sistema usará automaticamente `aes-256-gcm@openssl` (com tag presente).

---

## Requisitos

- PHP 8.x
- Laravel
- ext-sodium (recomendado) ou ext-openssl
- Docker & Docker Compose (para ambiente de desenvolvimento)

## Segurança

- Autenticação via HTTP Basic (considere OAuth2/JWT para produção)
- SQLs cifrados em trânsito
- Chaves rotacionáveis
- Auditoria completa de ações
- Logs estruturados sem exposição de dados sensíveis

---

**Desenvolvido com Laravel** | **Documentação atualizada em 04/10/2025**