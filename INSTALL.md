# Instalação detalhada - OurCloud Louvor

Este guia usa o instalador automatizado em **Ubuntu Server 24.04 LTS**. Ele é indicado para uma VM nova e suporta `amd64` e `arm64`.

> Para atualizar uma instalação existente, use [UPDATE.md](UPDATE.md), não este procedimento.

## 1. Requisitos da VM

Recomendado:

- Ubuntu Server 24.04 LTS;
- 2 vCPUs ou mais;
- 2 GB RAM no mínimo, 4 GB recomendado;
- 20 GB de disco;
- IP público;
- usuário com `sudo`;
- acesso à Internet durante a instalação.

Confirme:

```bash
cat /etc/os-release
dpkg --print-architecture
```

Valores suportados:

```text
Ubuntu 24.04
amd64 ou arm64
```

## 2. Prepare o domínio

Use um subdomínio, por exemplo:

```text
louvor.minhaigreja.org.br
```

Crie um registro DNS `A` apontando para o IP público da VM.

Depois de instalar `dnsutils`, você pode conferir:

```bash
sudo apt update
sudo apt install -y dnsutils

dig +short A louvor.minhaigreja.org.br
```

O resultado deve ser o IP público da nova VM.

## 3. Libere portas na nuvem

Permita entrada TCP:

- `22` - SSH;
- `80` - HTTP e validação ACME;
- `443` - HTTPS.

Nunca libere PostgreSQL `5432` para `0.0.0.0/0`.

## Firewall local e Oracle Cloud

Algumas imagens da Oracle Cloud usam regras `iptables` que aceitam SSH e rejeitam outras portas, mesmo quando a Security List/NSG já permite 80/443.

Antes de alterar regras, veja:

```bash
sudo iptables -L INPUT -n -v --line-numbers
```

Se houver um `REJECT` geral após a regra de SSH e **não** houver ACCEPT para 80/443, adicione as regras antes do REJECT:

```bash
sudo iptables -I INPUT 1 -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 2 -p tcp --dport 443 -j ACCEPT
```

Para persistir no Ubuntu:

```bash
sudo apt install -y iptables-persistent
sudo netfilter-persistent save
```

Revise as regras de acordo com a política de segurança da sua infraestrutura. Não aplique comandos de firewall sem entender o ambiente.

## 4. Conecte à VM

```bash
ssh ubuntu@IP_PUBLICO_DA_VM
```

## 5. Obtenha o código

### Via Git

```bash
sudo apt update
sudo apt install -y git
cd /home/ubuntu

git clone https://github.com/SEU_USUARIO/ourcloud-louvor.git
cd ourcloud-louvor
```

### Via ZIP da release

```bash
sudo apt update
sudo apt install -y unzip
cd /home/ubuntu
unzip OurCloud-Louvor-v1.0.0-rc88-GitHub.zip
cd ourcloud-louvor
```

Confirme:

```bash
cat VERSION
```

Esperado:

```text
1.0.0-rc88
```

## 6. Execute a instalação

```bash
sudo bash deploy/install.sh \
  --domain louvor.minhaigreja.org.br \
  --admin-email admin@minhaigreja.org.br \
  --organization "Minha Igreja"
```

O instalador gera a senha inicial automaticamente. Se quiser informar uma senha:

```bash
sudo bash deploy/install.sh \
  --domain louvor.minhaigreja.org.br \
  --admin-email admin@minhaigreja.org.br \
  --admin-password 'SenhaInicial123!' \
  --organization "Minha Igreja"
```

A senha inicial deve possuir pelo menos 12 caracteres.

### Parâmetros úteis

```text
--domain DOMINIO
--admin-email EMAIL
--admin-password SENHA
--admin-name NOME
--organization NOME
--organization-slug SLUG
--ssl-email EMAIL
--skip-https
--app-key-file ARQUIVO
--app-dir DIRETORIO
```

Veja todos:

```bash
bash deploy/install.sh --help
```

## 7. O que será instalado

O script provisiona automaticamente:

- PHP 8.3 CLI/FPM e extensões;
- PostgreSQL disponível no Ubuntu 24.04;
- Nginx;
- Composer;
- Node.js 24;
- Certbot;
- usuário do sistema `sgml`;
- banco `sgml`;
- `.env` de produção;
- migrations e seed estrutural;
- administrador inicial;
- pool PHP-FPM dedicado;
- worker de fila;
- scheduler;
- runtime de backup/restauração;
- assets do frontend;
- caches Laravel;
- vhost Nginx;
- HTTPS quando DNS/firewall permitem.

## 8. Segredos iniciais

Ao final:

```text
/root/sgml-install-secrets.txt
```

Leia:

```bash
sudo cat /root/sgml-install-secrets.txt
```

Guarde a senha do Administrador e outras informações necessárias em cofre seguro. Em seguida:

```bash
sudo shred -u /root/sgml-install-secrets.txt
```

## 9. Verifique Nginx e serviços

```bash
sudo nginx -t
sudo systemctl status nginx --no-pager
sudo systemctl status php8.3-fpm --no-pager
sudo systemctl status sgml-queue --no-pager
sudo systemctl status sgml-scheduler.timer --no-pager
```

Teste local sem depender do DNS:

```bash
curl -I http://127.0.0.1/login \
  -H 'Host: louvor.minhaigreja.org.br'
```

Um `200` ou redirecionamento HTTP válido indica que Nginx, PHP-FPM e Laravel estão respondendo.

## 10. HTTPS

O instalador tenta emitir o certificado automaticamente, salvo quando `--skip-https` é usado.

Se não conseguir, confira:

```bash
dig +short A louvor.minhaigreja.org.br
sudo ss -lntp | grep -E ':(80|443)\s'
sudo iptables -L INPUT -n -v --line-numbers
```

Depois:

```bash
cd /var/www/sgml
sudo bash deploy/enable-https-letsencrypt.sh \
  --domain louvor.minhaigreja.org.br \
  --email admin@minhaigreja.org.br
```

Teste:

```bash
curl -I https://louvor.minhaigreja.org.br/login
```

## 11. Validação técnica

```bash
sudo bash /var/www/sgml/scripts/verify-install.sh
```

Esperado:

```text
VALIDAÇÃO TÉCNICA: APROVADA
```

## 12. Primeiro login

Abra:

```text
https://louvor.minhaigreja.org.br
```

Use o e-mail informado em `--admin-email` e a senha guardada durante a instalação.

Depois:

1. revise o nome da organização;
2. cadastre pessoas/equipe;
3. configure papéis;
4. configure tipos de culto;
5. configure SMTP/WhatsApp somente se necessário;
6. gere o primeiro backup.

## 13. Primeiro backup

Na interface:

```text
Administração → Backup e restauração
```

Ou confira o runtime:

```bash
sudo -u sgml test -w /var/backups/sgml/database && echo OK
```

## 14. Problemas comuns

### Certbot não consegue validar o domínio

Normalmente é DNS, Security List/NSG ou firewall local. Leia [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

### `Permission denied` no `.env`

É esperado para usuário comum. O `.env` de produção é protegido. Use `sudo` apenas quando realmente precisar inspecionar uma variável específica.

### PostgreSQL

O instalador de uma VM limpa usa o PostgreSQL disponível para Ubuntu 24.04. Se você estiver **migrando** um dump de outra VM, leia [MIGRATION.md](MIGRATION.md) antes: não restaure um dump originado em major mais nova para servidor PostgreSQL mais antigo.
