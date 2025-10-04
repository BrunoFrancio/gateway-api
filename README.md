# Gateway API

Sistema para gerenciamento de gateways com suporte a enfileiramento de SQLs cifrados para trânsito seguro e execução por agente local.

## Índice

- [Visão Geral](#visão-geral)
- [Infraestrutura](#infraestrutura)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Sistema de Chaves e Segurança](#sistema-de-chaves-e-segurança)
- [Autenticação](#autenticação)
- [Endpoints da API](#endpoints-da-api)
  - [Healthcheck](#healthcheck)
  - [Gateways](#gateways)
  - [Jobs de SQL](#jobs-de-sql)
- [Criptografia em Trânsito](#criptografia-em-trânsito)
- [Cliente Python](#cliente-python)
- [Tracking de Atividade](#tracking-de-atividade)
- [Auditoria e Logs](#auditoria-e-logs)
- [Testes](#testes)
- [Troubleshooting](#troubleshooting)

---

## Visão Geral

A Gateway API permite:

- **Gerenciar gateways** com CRUD completo e rotação de chaves
- **Enfileirar SQLs criptografados** para execução remota segura
- **Tracking automático** de atividade dos gateways
- **Auditoria completa** de todas as operações

### Fluxo de Operação

1. **Admin** cria gateway via API e recebe chave (única vez)
2. **Agente local** armazena chave e inicia polling
3. **Admin** envia SQLs que são criptografados automaticamente
4. **Agente local** busca jobs pendentes, descriptografa e executa
5. **Agente local** confirma sucesso (ACK) ou falha

---

## Infraestrutura

- **Web Server:** Nginx 1.25 (proxy reverso)
- **Application Server:** PHP 8.2-FPM
- **Database:** PostgreSQL 17.5
- **Orquestração:** Docker Compose
- **Healthchecks:** Configurados para todos os serviços

### Arquitetura

```
┌─────────────┐
│   Cliente   │
└──────┬──────┘
       │ HTTPS (443) / HTTP (8080)
       ▼
┌─────────────┐
│    Nginx    │ (proxy reverso)
└──────┬──────┘
       │ FastCGI (9000)
       ▼
┌─────────────┐
│  PHP-FPM    │ (aplicação Laravel)
└──────┬──────┘
       │ PostgreSQL (5432)
       ▼
┌─────────────┐
│ PostgreSQL  │ (banco de dados)
└─────────────┘
```

---

## Requisitos

### Servidor

- Docker 20+
- Docker Compose 2+
- 2GB RAM mínimo
- 10GB espaço em disco

### Cliente/Agente Local

- Python 3.8+
- Bibliotecas: `requests`, `cryptography`

### Opcional

- Let's Encrypt para SSL automático
- Redis para filas (futuro)

---

## Instalação

### 1. Clonar Repositório

```bash
git clone <repo-url>
cd gateway-api
```

### 2. Configurar Ambiente

```bash
cp .env.example .env
```

Editar `.env`:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080/api

DB_HOST=db
DB_DATABASE=gateway_api
DB_USERNAME=gateway_api
DB_PASSWORD=sua_senha_segura

APP_PORT=8080
```

### 3. Subir Containers

```bash
docker-compose up -d
```

### 4. Instalar Dependências

```bash
docker exec app composer install
```

### 5. Rodar Migrations

```bash
docker exec app php artisan migrate --force
```

### 6. Criar Usuário Admin

```bash
docker exec -it app php artisan tinker
```

```php
\App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@admin.com',
    'password' => bcrypt('senha_segura')
]);
exit
```

### 7. Verificar Instalação

```bash
curl http://localhost:8080/api/health
# Resposta: {"status":"ok"}
```

---

## Sistema de Chaves e Segurança

### Importante: Exposição da Chave

A chave criptográfica (`key_material`) é exposta **APENAS**:

1. **Na criação do gateway** (`POST /api/gateways`)
2. **Na rotação de chave** (`PATCH /api/gateways/{id}/rotate`)

**Nunca** é retornada em:
- `GET /api/gateways` (listagem)
- `GET /api/gateways/{id}` (detalhes)
- `PATCH /api/gateways/{id}` (atualização)

### Exemplo: Criação de Gateway

**Request:**

```bash
curl -u admin@admin.com:senha \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8080/api/gateways \
  -d '{"nome":"gateway-producao","ativo":true}'
```

**Response (única vez que a chave aparece):**

```json
{
  "data": {
    "id": "01k6r329jtd08nmnw6q4kgpwqn",
    "nome": "gateway-producao",
    "key_id": "key_v1",
    "key_material": "PN/jor+IhBLaC4WEzaNdgr6Jy621VJjDjzNye4FGpvo=",
    "created_at": "2025-10-04T17:07:15.000000Z"
  },
  "message": "Gateway criado com sucesso. ATENÇÃO: Armazene a chave de forma segura, ela não será exibida novamente!"
}
```

### Armazenamento no Agente Local

O agente deve armazenar `key_material` em:

```bash
# .env do agente
GATEWAY_KEY_MATERIAL=PN/jor+IhBLaC4WEzaNdgr6Jy621VJjDjzNye4FGpvo=
```

Ou variável de ambiente:

```bash
export GATEWAY_KEY_MATERIAL="PN/jor+IhBLaC4WEzaNdgr6Jy621VJjDjzNye4FGpvo="
```

**Nunca** commitar chaves no código-fonte.

### Rotação de Chaves

Ao rotacionar, a nova chave é retornada:

```bash
curl -u admin@admin.com:senha \
  -X PATCH http://localhost:8080/api/gateways/{id}/rotate
```

```json
{
  "data": {
    "key_id": "key_v2",
    "key_material": "nova_chave_base64_aqui...",
    "key_rotated_at": "2025-10-04T17:30:00.000000Z"
  },
  "message": "Chave rotacionada com sucesso. ATENÇÃO: Atualize a chave no gateway local imediatamente!"
}
```

O agente **deve** atualizar sua configuração local com a nova chave.

### Validação de Nome do Gateway

- **Formato:** apenas letras minúsculas, números, hífen e underscore
- **Regex:** `^[a-z0-9\-_]+$`
- **Único** na tabela
- **Tamanho:** mínimo 3, máximo 100 caracteres

Exemplos válidos: `gateway-sp`, `gw_producao_01`, `estacao-rj-2024`

---

## Autenticação

**Método:** HTTP Basic Authentication

**Headers obrigatórios:**
- `Accept: application/json`
- `Content-Type: application/json` (quando houver corpo)

**Exemplo:**

```bash
curl -u admin@admin.com:senha \
  -H "Accept: application/json" \
  http://localhost:8080/api/gateways
```

**Nota:** Em produção, considere OAuth2, JWT ou Laravel Sanctum para autenticação mais robusta.

---

## Endpoints da API

### Healthcheck

**GET** `/api/health`

Verifica se a aplicação está funcionando.

```bash
curl http://localhost:8080/api/health
```

**Resposta:**

```json
{
  "status": "ok"
}
```

---

### Gateways

#### Listar Gateways

**GET** `/api/gateways`

**Parâmetros de Query:**
- `ativo`: `1` (true) ou `0` (false)
- `search`: busca por nome
- `page`: número da página
- `per_page`: itens por página (padrão: 15)

**Exemplo:**

```bash
curl -u admin@admin.com:senha \
  "http://localhost:8080/api/gateways?ativo=1&per_page=10"
```

**Resposta:**

```json
{
  "data": [
    {
      "id": "01k6p5ezwvnj2j9zjazztp48gz",
      "nome": "gateway-producao",
      "ativo": true,
      "key_id": "key_v1",
      "key_alg": "app-key@laravel-crypt",
      "key_rotated_at": null,
      "last_seen_at": "2025-10-04T17:05:00.000000Z",
      "observacoes": null,
      "created_at": "2025-10-03T23:10:39.000000Z",
      "updated_at": "2025-10-04T17:05:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 1,
    "last_page": 1
  }
}
```

**Nota:** `key_material` **nunca** aparece neste endpoint.

#### Criar Gateway

**POST** `/api/gateways`

**Body:**

```json
{
  "nome": "gateway-sp-01",
  "ativo": true,
  "observacoes": "Gateway da filial SP"
}
```

**Exemplo:**

```bash
curl -u admin@admin.com:senha \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8080/api/gateways \
  -d '{"nome":"gateway-sp-01","ativo":true}'
```

**Resposta (única vez com key_material):**

```json
{
  "data": {
    "id": "01k6r329jtd08nmnw6q4kgpwqn",
    "nome": "gateway-sp-01",
    "ativo": true,
    "key_id": "key_v1",
    "key_alg": "app-key@laravel-crypt",
    "key_material": "1bvF9syKJ7b+533dZ+adZIBbDl4Wqz3YsBQ3Q9iU5Vs=",
    "observacoes": "Gateway da filial SP",
    "created_at": "2025-10-04T17:07:15.000000Z"
  },
  "message": "Gateway criado com sucesso. ATENÇÃO: Armazene a chave de forma segura, ela não será exibida novamente!"
}
```

#### Visualizar Gateway

**GET** `/api/gateways/{id}`

```bash
curl -u admin@admin.com:senha \
  http://localhost:8080/api/gateways/01k6r329jtd08nmnw6q4kgpwqn
```

**Nota:** `key_material` **não** é incluído na resposta.

#### Atualizar Gateway

**PATCH** `/api/gateways/{id}`

**Body:**

```json
{
  "nome": "gateway-sp-01-updated",
  "ativo": false,
  "observacoes": "Em manutenção"
}
```

```bash
curl -u admin@admin.com:senha \
  -H "Content-Type: application/json" \
  -X PATCH http://localhost:8080/api/gateways/{id} \
  -d '{"nome":"gateway-sp-01-updated","ativo":false}'
```

#### Rotacionar Chave

**PATCH** `/api/gateways/{id}/rotate`

Gera uma nova versão da chave (`key_v2`, `key_v3`, etc).

```bash
curl -u admin@admin.com:senha \
  -X PATCH http://localhost:8080/api/gateways/{id}/rotate
```

**Resposta:**

```json
{
  "data": {
    "id": "01k6r329jtd08nmnw6q4kgpwqn",
    "nome": "gateway-sp-01",
    "key_id": "key_v2",
    "key_material": "PmQ+YUrPt41F0QlBnueUE5Vr7IP/9zUBeAL+Q8cgG24=",
    "key_rotated_at": "2025-10-04T17:07:58.000000Z"
  },
  "message": "Chave rotacionada com sucesso. ATENÇÃO: Atualize a chave no gateway local imediatamente!"
}
```

---

### Jobs de SQL

#### Criar Job de SQL

**POST** `/api/gateways/{id}/sql-jobs`

**Body:**

```json
{
  "sql": "UPDATE contas SET ativo = true WHERE id = 123;",
  "disponivel_em": null
}
```

**Exemplo:**

```bash
curl -u admin@admin.com:senha \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8080/api/gateways/{id}/sql-jobs \
  -d '{"sql":"UPDATE contas SET ativo = true WHERE id = 123;"}'
```

**Resposta:**

```json
{
  "data": {
    "id": "01k6qnta4nqnxqa79nb1j7thbg",
    "gateway_id": "01k6r329jtd08nmnw6q4kgpwqn",
    "status": "pending",
    "transit_alg": "xchacha20poly1305@libsodium",
    "key_id": "key_v1",
    "disponivel_em": null,
    "created_at": "2025-10-04T13:15:42.000000Z"
  }
}
```

#### Listar Jobs Pendentes

**GET** `/api/gateways/{id}/sql-jobs/pending?limit=10`

**Parâmetros:**
- `limit`: máximo de jobs a retornar (padrão: 10, máx: 100)

```bash
curl -u admin@admin.com:senha \
  "http://localhost:8080/api/gateways/{id}/sql-jobs/pending?limit=10"
```

**Resposta:**

```json
{
  "data": [
    {
      "id": "01k6qnta4nqnxqa79nb1j7thbg",
      "gateway_id": "01k6r329jtd08nmnw6q4kgpwqn",
      "status": "sent",
      "transit_alg": "xchacha20poly1305@libsodium",
      "key_id": "key_v1",
      "ciphertext": "eMbIwOG8/VTzrtCLmD9JtB21ZIBD64mlNf1E4Y0z2+rehequD83HSHc1tzQGvyh3",
      "nonce": "FYoPUcbTUGhwWdpjSJG9uHQixRSTJzI4",
      "tag": null,
      "disponivel_em": null,
      "created_at": "2025-10-04T13:15:42.000000Z"
    }
  ]
}
```

**Nota:** O status muda automaticamente para `sent` quando o job é retornado.

#### Confirmar Processamento (ACK)

**POST** `/api/gateways/{id}/sql-jobs/{job}/ack`

```bash
curl -u admin@admin.com:senha \
  -X POST http://localhost:8080/api/gateways/{id}/sql-jobs/{job_id}/ack
```

**Resposta:**

```json
{
  "message": "ACK registrado com sucesso"
}
```

#### Registrar Falha

**POST** `/api/gateways/{id}/sql-jobs/{job}/fail`

**Body:**

```json
{
  "mensagem": "Erro de sintaxe SQL na linha 5"
}
```

```bash
curl -u admin@admin.com:senha \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8080/api/gateways/{id}/sql-jobs/{job_id}/fail \
  -d '{"mensagem":"Erro de sintaxe SQL"}'
```

**Resposta:**

```json
{
  "message": "Falha registrada"
}
```

---

## Criptografia em Trânsito

O sistema usa **AEAD** (Authenticated Encryption with Associated Data) para garantir confidencialidade e integridade.

### Algoritmo Preferencial: libsodium

**Nome:** `xchacha20poly1305@libsodium`

**Características:**
- Cipher: XChaCha20 (stream cipher)
- MAC: Poly1305 (autenticação)
- Nonce: 24 bytes (192 bits)
- Tag: embutido no ciphertext

**Campos retornados:**
- `ciphertext`: SQL criptografado + tag (base64)
- `nonce`: nonce usado (base64)
- `tag`: `null` (já incluído no ciphertext)

### Algoritmo Fallback: OpenSSL

**Nome:** `aes-256-gcm@openssl`

**Características:**
- Cipher: AES-256 em modo GCM
- MAC: GCM authentication tag
- Nonce: 12 bytes (96 bits)
- Tag: separado do ciphertext

**Campos retornados:**
- `ciphertext`: SQL criptografado (base64)
- `nonce`: IV usado (base64)
- `tag`: authentication tag (base64)

### Descriptografia

O agente deve:

1. Verificar `transit_alg` do job
2. Usar a chave correspondente ao `key_id`
3. Decodificar base64 dos campos
4. Descriptografar usando algoritmo apropriado

---

## Cliente Python

### Instalação

```bash
pip install requests cryptography
```

### Script de Exemplo

**Arquivo: `gateway_client.py`**

```python
#!/usr/bin/env python3
import requests
from gateway_crypto import GatewayCrypto

# Configuração
API_URL = "http://localhost:8080/api/gateways"
GATEWAY_ID = "01k6r329jtd08nmnw6q4kgpwqn"
API_USER = "admin@admin.com"
API_PASS = "senha"
KEY_MATERIAL = "1bvF9syKJ7b+533dZ+adZIBbDl4Wqz3YsBQ3Q9iU5Vs="

# Inicializar cliente de criptografia
crypto = GatewayCrypto(KEY_MATERIAL)

# Buscar jobs pendentes
response = requests.get(
    f"{API_URL}/{GATEWAY_ID}/sql-jobs/pending",
    auth=(API_USER, API_PASS),
    params={"limit": 10}
)

jobs = response.json()['data']

# Processar cada job
for job in jobs:
    job_id = job['id']
    
    try:
        # Descriptografar SQL
        sql = crypto.decrypt_sql_job(job)
        print(f"SQL: {sql}")
        
        # Executar no banco local
        # cursor.execute(sql)
        # conn.commit()
        
        # Confirmar sucesso
        requests.post(
            f"{API_URL}/{GATEWAY_ID}/sql-jobs/{job_id}/ack",
            auth=(API_USER, API_PASS)
        )
        
    except Exception as e:
        # Registrar falha
        requests.post(
            f"{API_URL}/{GATEWAY_ID}/sql-jobs/{job_id}/fail",
            auth=(API_USER, API_PASS),
            json={"mensagem": str(e)}
        )
```

### Módulo de Criptografia

**Arquivo: `gateway_crypto.py`**

```python
import base64
from cryptography.hazmat.primitives.ciphers.aead import AESGCM, ChaCha20Poly1305

class GatewayCrypto:
    def __init__(self, key_material_b64: str):
        self.key = base64.b64decode(key_material_b64)
        if len(self.key) != 32:
            raise ValueError("Chave deve ter 32 bytes")
    
    def decrypt_sql_job(self, job: dict) -> str:
        alg = job['transit_alg']
        
        if alg == 'xchacha20poly1305@libsodium':
            return self._decrypt_xchacha20(job)
        elif alg == 'aes-256-gcm@openssl':
            return self._decrypt_aes_gcm(job)
        else:
            raise ValueError(f"Algoritmo não suportado: {alg}")
    
    def _decrypt_xchacha20(self, job: dict) -> str:
        cipher = ChaCha20Poly1305(self.key)
        nonce = base64.b64decode(job['nonce'])
        ciphertext = base64.b64decode(job['ciphertext'])
        
        plaintext = cipher.decrypt(nonce, ciphertext, None)
        return plaintext.decode('utf-8')
    
    def _decrypt_aes_gcm(self, job: dict) -> str:
        cipher = AESGCM(self.key)
        nonce = base64.b64decode(job['nonce'])
        ciphertext = base64.b64decode(job['ciphertext'])
        tag = base64.b64decode(job['tag'])
        
        plaintext = cipher.decrypt(nonce, ciphertext + tag, None)
        return plaintext.decode('utf-8')
```

---

## Tracking de Atividade

O campo `last_seen_at` é atualizado automaticamente quando o gateway faz requisições em rotas que incluem `{gateway}`:

- `GET /api/gateways/{id}`
- `PATCH /api/gateways/{id}`
- `PATCH /api/gateways/{id}/rotate`
- `POST /api/gateways/{id}/sql-jobs`
- `GET /api/gateways/{id}/sql-jobs/pending`
- `POST /api/gateways/{id}/sql-jobs/{job}/ack`
- `POST /api/gateways/{id}/sql-jobs/{job}/fail`

**Nota:** Rotas de listagem (`GET /api/gateways`) **não** atualizam `last_seen_at`.

### Monitorar Gateways Inativos

```bash
# Listar gateways que não aparecem há mais de 1 hora
curl -u admin@admin.com:senha \
  "http://localhost:8080/api/gateways" | \
  jq '.data[] | select(.last_seen_at < (now - 3600))'
```

---

## Auditoria e Logs

### Canais de Log

**SQL Jobs:**
- Canal: `gateway_sql`
- Arquivos: `storage/logs/gateway-sql-*.log`
- Eventos: `sql_job_criado`, `sql_job_enviado`, `sql_job_ack`, `sql_job_falha`
- Retenção: 30 dias

**Auditoria de Chaves:**
- Canal: `gateway_audit`
- Arquivos: `storage/logs/gateway-audit-*.log`
- Eventos: `criar_chave`, `rotacionar_chave`
- Retenção: 90 dias

### Ver Logs

```bash
# Logs de SQL jobs
docker exec app tail -f storage/logs/gateway-sql.log

# Logs de auditoria
docker exec app tail -f storage/logs/gateway-audit.log

# Logs gerais da aplicação
docker exec app tail -f storage/logs/laravel.log
```

### Exemplo de Log de SQL Job

```json
{
  "timestamp": "2025-10-04T13:15:42.000000Z",
  "level": "info",
  "message": "sql_job_criado",
  "context": {
    "job_id": "01k6qnta4nqnxqa79nb1j7thbg",
    "gateway_id": "01k6r329jtd08nmnw6q4kgpwqn",
    "alg": "xchacha20poly1305@libsodium",
    "key_id": "key_v1",
    "status": "pending"
  }
}
```

**Importante:** O material da chave e o SQL em texto plano **nunca** são logados.

---

## Testes

### Script End-to-End

**Arquivo:** `test_gateway_flow.sh`

Testa fluxo completo: criação, listagem, criptografia, ACK e rotação.

```bash
chmod +x test_gateway_flow.sh
./test_gateway_flow.sh
```

**Saída esperada:**

```
✅ Health check OK
✅ Gateway criado com sucesso
✅ Segurança OK: key_material não é exposta no GET
✅ SQL Job criado com sucesso
✅ Job retornado com dados criptografados
✅ ACK confirmado com sucesso
✅ Chave rotacionada com sucesso
```

### Testes Manuais

#### 1. Criar Gateway

```bash
curl -u admin@admin.com:senha \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8080/api/gateways \
  -d '{"nome":"test-gateway","ativo":true}' | jq .
```

Salvar `key_material` retornado.

#### 2. Enviar SQL Job

```bash
GATEWAY_ID="<id_do_gateway_criado>"

curl -u admin@admin.com:senha \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8080/api/gateways/$GATEWAY_ID/sql-jobs \
  -d '{"sql":"SELECT 1"}' | jq .
```

#### 3. Buscar Jobs Pendentes

```bash
curl -u admin@admin.com:senha \
  "http://localhost:8080/api/gateways/$GATEWAY_ID/sql-jobs/pending" | jq .
```

#### 4. Testar Cliente Python

```bash
# Editar gateway_client.py com GATEWAY_ID e KEY_MATERIAL
python3 gateway_client.py
```

---

## Troubleshooting

### Erro 404: /api/health

**Problema:** Rota não encontrada.

**Solução:**
```bash
# Limpar cache de rotas
docker exec app php artisan route:clear
docker exec app php artisan config:clear

# Verificar rotas registradas
docker exec app php artisan route:list | grep health
```

### Erro 401: Unauthorized

**Problema:** Credenciais inválidas.

**Solução:**
```bash
# Verificar usuário existe
docker exec -it app php artisan tinker
```

```php
\App\Models\User::where('email', 'admin@admin.com')->first();
```

### Erro 405: Method Not Allowed

**Problema:** Método HTTP incorreto.

**Solução:** Verificar se está usando o método correto:
- Rotação de chave: `PATCH` (não `POST`)
- Criação de gateway: `POST`
- Listagem: `GET`

### Erro: The payload is invalid (DecryptException)

**Problema:** Falha ao descriptografar.

**Causas possíveis:**
1. `key_id` do job não corresponde à chave usada pelo agente
2. Chave base64 incorreta
3. Extensão libsodium não disponível

**Solução:**
```bash
# Verificar extensão PHP
docker exec app php -m | grep sodium

# Verificar chave do gateway
curl -u admin@admin.com:senha \
  http://localhost:8080/api/gateways/{id} | jq '.data.key_id'
```

### Container não sobe

**Problema:** Erro ao iniciar Docker Compose.

**Solução:**
```bash
# Ver logs
docker-compose logs app

# Reconstruir imagem
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Permissões negadas em storage/

**Problema:** Laravel não consegue escrever logs.

**Solução:**
```bash
docker exec app chown -R apiuser:apiuser /var/www/html/storage
docker exec app chown -R apiuser:apiuser /var/www/html/bootstrap/cache
```

### Jobs ficam em status 'sent' permanentemente

**Problema:** Agente não está enviando ACK/FAIL.

**Solução:**
1. Verificar se agente está rodando
2. Verificar logs do agente
3. Confirmar que `key_material` está correto
4. Testar descriptografia manualmente

### Nginx retorna 502 Bad Gateway

**Problema:** PHP-FPM não está respondendo.

**Solução:**
```bash
# Verificar status do PHP-FPM
docker exec app php-fpm -t

# Ver logs do Nginx
docker exec proxy tail -f /var/log/nginx/error.log

# Reiniciar container
docker-compose restart app
```

---

## Segurança

### Checklist de Produção

- [ ] SSL/TLS configurado (Let's Encrypt)
- [ ] Firewall restringindo portas (apenas 80/443)
- [ ] Senhas fortes para usuários
- [ ] Autenticação robusta (considerar JWT/OAuth2)
- [ ] Rate limiting configurado
- [ ] Logs monitorados
- [ ] Backup automático do banco
- [ ] Rotação periódica de chaves dos gateways
- [ ] Variáveis de ambiente protegidas
- [ ] `.env` nunca commitado no git

### Boas Práticas

1. **Chaves:**
   - Nunca commitar `key_material` no código
   - Armazenar em variáveis de ambiente ou secrets manager
   - Rotacionar periodicamente (a cada 90 dias recomendado)

2. **Autenticação:**
   - Usar HTTPS em produção (obrigatório)
   - Considerar JWT ou Laravel Sanctum
   - Implementar rate limiting

3. **Logs:**
   - Monitorar tentativas de ACK/FAIL excessivas
   - Alertar sobre gateways inativos (>24h sem last_seen_at)
   - Revisar logs de auditoria regularmente

4. **Rede:**
   - Restringir acesso à API apenas IPs confiáveis
   - Usar VPN para comunicação gateway ↔ API
   - Firewall no nível de container/host

---

## Licença

Proprietary - Todos os direitos reservados

---

## Suporte

Para problemas ou dúvidas:

1. Verificar seção [Troubleshooting](#troubleshooting)
2. Consultar logs da aplicação
3. Abrir issue no repositório (se aplicável)

---

**Desenvolvido com Laravel 11 + PHP 8.2-FPM + PostgreSQL 17.5**

**Documentação atualizada em 04/10/2025**