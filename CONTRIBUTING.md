# Contribuindo com o OurCloud Louvor

Obrigado por considerar uma contribuição.

## Antes de começar

- Use Issues para bugs e propostas públicas.
- Não publique segredos, dumps, credenciais ou dados pessoais.
- Vulnerabilidades com impacto de segurança devem seguir `SECURITY.md`.
- Prefira mudanças pequenas, testáveis e fáceis de revisar.

## Fluxo recomendado

1. Faça um fork do repositório.
2. Atualize sua branch `main`.
3. Crie uma branch descritiva:

```bash
git checkout -b feat/nova-funcionalidade
```

ou:

```bash
git checkout -b fix/correcao-especifica
```

4. Instale as dependências:

```bash
composer install
npm ci
```

5. Faça sua alteração.
6. Adicione ou atualize testes.
7. Execute:

```bash
composer test
npm run build
```

8. Confira o diff:

```bash
git status
git diff
```

9. Faça commit com mensagem objetiva.
10. Abra um Pull Request.

## Padrão de commits sugerido

Exemplos:

```text
feat: adicionar filtro de repertório
fix: corrigir permissão de agenda
refactor: simplificar serviço de disponibilidade
docs: melhorar guia de instalação
test: cobrir alteração de perfil
```

## Requisitos para Pull Request

O PR deve explicar:

- qual problema resolve;
- como a solução funciona;
- impacto em banco/migrations;
- impacto em permissões;
- impacto em instalação/atualização;
- como foi testado;
- screenshots quando houver mudança visual relevante.

## Banco de dados

Se precisar alterar a estrutura:

- crie uma migration nova;
- não altere migrations antigas já publicadas;
- preserve dados existentes;
- mantenha compatibilidade PostgreSQL;
- cubra a migração com testes/smoke quando possível.

## UI/UX

Mantenha:

- experiência responsiva;
- linguagem em português clara;
- acessibilidade por texto/ícone, sem depender apenas de cor;
- botões e feedbacks consistentes;
- perfis e permissões respeitados no servidor, não apenas na interface.

## Segurança

Nunca faça commit de:

```text
.env
*.dump
*.sql
*.pem
*.key
sgml-app-key.txt
sgml-install-secrets.txt
```

O `.gitignore` contém proteções adicionais, mas ele não substitui revisão manual.
