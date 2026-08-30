# Política de Segurança

## Relatando uma vulnerabilidade

Não publique uma vulnerabilidade explorável em Issue público.

Quando o repositório estiver no GitHub, prefira **Security → Advisories → Report a vulnerability**, se o mantenedor tiver habilitado o recurso.

Inclua, de forma privada:

- versão afetada;
- impacto;
- passos mínimos de reprodução;
- pré-condições;
- sugestão de correção, se houver.

Nunca envie banco de produção, `.env`, APP_KEY ou credenciais como prova.

## Segredos que nunca devem entrar no Git

- `.env`;
- APP_KEY;
- senha do PostgreSQL;
- backups `.dump`/`.sql`;
- `sgml-install-secrets.txt`;
- tokens Google/WhatsApp/SMTP;
- certificados/chaves privadas;
- cookies/sessões;
- dados reais de membros;
- logs de produção sem sanitização.

Antes de publicar:

```bash
git status
git diff --cached
```

## Exposição de rede

Em produção, exponha apenas o necessário:

```text
22/tcp   SSH (preferencialmente restrito)
80/tcp   HTTP / ACME
443/tcp  HTTPS
```

Não exponha PostgreSQL `5432` publicamente.

## APP_KEY

A APP_KEY é um segredo estrutural do Laravel. Em migração, preserve a mesma chave por canal seguro quando precisar manter dados criptografados legíveis.

## Backups

Backups contêm dados reais e devem ser tratados como informação sensível. Guarde cópias externas, proteja acesso e valide restaurações periodicamente.

## Atualizações

Mantenha o sistema operacional atualizado e acompanhe advisories das dependências PHP/JavaScript.
