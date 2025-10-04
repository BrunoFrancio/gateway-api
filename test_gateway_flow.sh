#!/bin/bash
set -e

API_URL="http://localhost:8080/api"
API_USER="admin@admin.com"
API_PASS="senha"

echo "🧪 Testando fluxo completo da Gateway API"
echo "========================================="

# Cores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Testar Health Check
echo -e "\n${YELLOW}1. Testando Health Check${NC}"
HEALTH=$(curl -s -w "\n%{http_code}" "$API_URL/health")
HTTP_CODE=$(echo "$HEALTH" | tail -n 1)
RESPONSE=$(echo "$HEALTH" | head -n -1)

if [ "$HTTP_CODE" -eq 200 ]; then
    echo -e "${GREEN}✅ Health check OK${NC}"
    echo "$RESPONSE" | jq '.'
else
    echo -e "${RED}❌ Health check falhou (HTTP $HTTP_CODE)${NC}"
    exit 1
fi

# 2. Criar Gateway
echo -e "\n${YELLOW}2. Criando novo gateway${NC}"
GATEWAY_NAME="test-gateway-$(date +%s)"

CREATE_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -u "$API_USER:$API_PASS" \
    -X POST "$API_URL/gateways" \
    -H "Content-Type: application/json" \
    -d "{\"nome\":\"$GATEWAY_NAME\",\"ativo\":true,\"observacoes\":\"Gateway de teste\"}")

HTTP_CODE=$(echo "$CREATE_RESPONSE" | tail -n 1)
RESPONSE=$(echo "$CREATE_RESPONSE" | head -n -1)

if [ "$HTTP_CODE" -eq 201 ]; then
    echo -e "${GREEN}✅ Gateway criado com sucesso${NC}"
    echo "$RESPONSE" | jq '.'
    
    # Extrair dados importantes
    GATEWAY_ID=$(echo "$RESPONSE" | jq -r '.data.id')
    KEY_MATERIAL=$(echo "$RESPONSE" | jq -r '.data.key_material')
    
    echo -e "\n${YELLOW}📋 Dados do Gateway:${NC}"
    echo "  ID: $GATEWAY_ID"
    echo "  Nome: $GATEWAY_NAME"
    echo "  Chave: ${KEY_MATERIAL:0:20}... (truncada)"
    
    # Salvar chave em arquivo temporário para testes
    echo "$KEY_MATERIAL" > /tmp/gateway_key_material.txt
    echo -e "${GREEN}✅ Chave salva em /tmp/gateway_key_material.txt${NC}"
else
    echo -e "${RED}❌ Erro ao criar gateway (HTTP $HTTP_CODE)${NC}"
    echo "$RESPONSE" | jq '.'
    exit 1
fi

# 3. Listar Gateways
echo -e "\n${YELLOW}3. Listando gateways${NC}"
LIST_RESPONSE=$(curl -s -u "$API_USER:$API_PASS" "$API_URL/gateways")
echo "$LIST_RESPONSE" | jq '.data[] | {id, nome, ativo, key_id}'

# 4. Verificar que key_material NÃO aparece no GET
echo -e "\n${YELLOW}4. Verificando que chave não é exposta no GET${NC}"
GET_RESPONSE=$(curl -s -u "$API_USER:$API_PASS" "$API_URL/gateways/$GATEWAY_ID")
HAS_KEY=$(echo "$GET_RESPONSE" | jq 'has("key_material")')

if [ "$HAS_KEY" = "false" ]; then
    echo -e "${GREEN}✅ Segurança OK: key_material não é exposta no GET${NC}"
else
    echo -e "${RED}❌ ATENÇÃO: key_material está sendo exposta no GET!${NC}"
fi

# 5. Enviar SQL Job
echo -e "\n${YELLOW}5. Enviando SQL Job${NC}"
SQL_COMMAND="SELECT * FROM users WHERE id = 1"

JOB_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -u "$API_USER:$API_PASS" \
    -X POST "$API_URL/gateways/$GATEWAY_ID/sql-jobs" \
    -H "Content-Type: application/json" \
    -d "{\"sql\":\"$SQL_COMMAND\"}")

HTTP_CODE=$(echo "$JOB_RESPONSE" | tail -n 1)
RESPONSE=$(echo "$JOB_RESPONSE" | head -n -1)

if [ "$HTTP_CODE" -eq 201 ]; then
    echo -e "${GREEN}✅ SQL Job criado com sucesso${NC}"
    echo "$RESPONSE" | jq '.'
    
    JOB_ID=$(echo "$RESPONSE" | jq -r '.data.id')
else
    echo -e "${RED}❌ Erro ao criar SQL Job (HTTP $HTTP_CODE)${NC}"
    echo "$RESPONSE" | jq '.'
    exit 1
fi

# 6. Buscar Jobs Pendentes
echo -e "\n${YELLOW}6. Buscando jobs pendentes${NC}"
PENDING_RESPONSE=$(curl -s \
    -u "$API_USER:$API_PASS" \
    "$API_URL/gateways/$GATEWAY_ID/sql-jobs/pending?limit=10")

echo "$PENDING_RESPONSE" | jq '.'

# Verificar se tem dados criptografados
CIPHERTEXT=$(echo "$PENDING_RESPONSE" | jq -r '.data[0].ciphertext // empty')
if [ -n "$CIPHERTEXT" ]; then
    echo -e "${GREEN}✅ Job retornado com dados criptografados${NC}"
    echo "  Algoritmo: $(echo "$PENDING_RESPONSE" | jq -r '.data[0].transit_alg')"
    echo "  Ciphertext: ${CIPHERTEXT:0:30}... (truncado)"
else
    echo -e "${RED}❌ Nenhum job pendente encontrado${NC}"
fi

# 7. Confirmar ACK do Job
echo -e "\n${YELLOW}7. Confirmando ACK do job${NC}"
ACK_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -u "$API_USER:$API_PASS" \
    -X POST "$API_URL/gateways/$GATEWAY_ID/sql-jobs/$JOB_ID/ack")

HTTP_CODE=$(echo "$ACK_RESPONSE" | tail -n 1)

if [ "$HTTP_CODE" -eq 200 ]; then
    echo -e "${GREEN}✅ ACK confirmado com sucesso${NC}"
else
    echo -e "${RED}❌ Erro ao confirmar ACK (HTTP $HTTP_CODE)${NC}"
fi

# 8. Rotação de Chave (opcional)
echo -e "\n${YELLOW}8. [OPCIONAL] Testando rotação de chave${NC}"
read -p "Deseja testar rotação de chave? (s/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Ss]$ ]]; then
    ROTATE_RESPONSE=$(curl -s -w "\n%{http_code}" \
        -u "$API_USER:$API_PASS" \
        -X PATCH "$API_URL/gateways/$GATEWAY_ID/rotate")
    
    HTTP_CODE=$(echo "$ROTATE_RESPONSE" | tail -n 1)
    RESPONSE=$(echo "$ROTATE_RESPONSE" | head -n -1)
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        echo -e "${GREEN}✅ Chave rotacionada com sucesso${NC}"
        echo "$RESPONSE" | jq '.'
        
        NEW_KEY=$(echo "$RESPONSE" | jq -r '.data.key_material')
        echo -e "\n${YELLOW}Nova chave: ${NEW_KEY:0:20}...${NC}"
    else
        echo -e "${RED}❌ Erro ao rotacionar chave (HTTP $HTTP_CODE)${NC}"
    fi
fi

echo -e "\n${GREEN}=========================================${NC}"
echo -e "${GREEN}✅ Todos os testes concluídos!${NC}"
echo -e "${GREEN}=========================================${NC}"

echo -e "\n${YELLOW}📝 Próximos passos:${NC}"
echo "  1. Teste o cliente Python com a chave salva em /tmp/gateway_key_material.txt"
echo "  2. Verifique os logs: docker logs app"
echo "  3. Limpe o gateway de teste se necessário"