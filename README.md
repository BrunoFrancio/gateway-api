# API de Gateways

## Autenticação e Headers

**Auth:** HTTP Basic (usuário/senha da tabela users).

**Headers obrigatórios em chamadas JSON:**

- `Accept: application/json`
- `Content-Type: application/json` (quando houver corpo)

**Exemplo de header + auth:**

```bash
curl -u admin@local.test:admin12345 \
  -H "Accept: application/json" \
  http://localhost:8080/api/gateways
```

> **Nota de produção:** em ambientes públicos, recomenda-se restringir a endpoints "admin-only" via Gate/Policy ou outro mecanismo.

---

## Endpoints

### Listar (com filtros e paginação)

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

### Criar

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

### Detalhar

**GET** `/api/gateways/{id}`

**Exemplo:**

```bash
curl -u admin@local.test:admin12345 -s \
  -H "Accept: application/json" \
  http://localhost:8080/api/gateways/01k6p9p7re7q9e83daaqv1jpyp
```

---

### Atualizar

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

### Rotacionar chave

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

## Auditoria & Logs

A criação e a rotação de chave geram registros de auditoria (tabela e/ou logs, conforme implementação).

**Logs** (se o canal dedicado estiver habilitado): `storage/logs/gateway_audit-*.log`

> **Importante:** o material da chave permanece cifrado em repouso e não é logado em texto puro.

**Ver últimos logs (exemplo):**

```bash
docker compose exec app sh -lc 'ls -l storage/logs && tail -n 50 storage/logs/gateway_audit-*.log'
```