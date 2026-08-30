# OurCloud Louvor

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16%2B-4169E1)
![License](https://img.shields.io/badge/license-AGPL--3.0-blue)
![Version](https://img.shields.io/badge/version-1.0.0--rc88-indigo)

**OurCloud Louvor** é um sistema open source para gestão de ministérios de louvor. Ele reúne agenda, escalas, repertórios, ensaios, disponibilidade, trocas, histórico de ministrações, tons por ministro, eventos, aniversariantes, notificações e integrações em uma única aplicação web responsiva.

> Versão: **1.0.0-rc88 - GitHub/Open Source Edition**  
> Licença: **GNU Affero General Public License v3.0 (AGPL-3.0)**

## Principais recursos

- gestão de pessoas, usuários, papéis e permissões;
- agenda institucional e agenda individual do ministro;
- criação, publicação e acompanhamento de escalas;
- disponibilidade recorrente, exceções e indisponibilidades;
- solicitações e acompanhamento de trocas;
- biblioteca de louvores, versões, artista, tom original, BPM e referências;
- repertório por culto com ministro responsável e tom planejado;
- **memória de tom por ministro + louvor**;
- ensaios com consulta/ajuste de tom;
- histórico de músicas efetivamente ministradas;
- eventos especiais;
- aniversariantes com controle por perfil;
- Central de Ações;
- avisos via WhatsApp com Evolution API ou Meta WhatsApp Cloud API;
- integração opcional com Google Calendar;
- calendário ICS individual;
- relatórios;
- backup e restauração do PostgreSQL;
- interface responsiva para desktop e celular;
- HTTPS com Let's Encrypt;
- fila e scheduler administrados por systemd.

## Tecnologias

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.3 / Laravel 13 |
| Interface | Blade, Livewire 4, Tailwind CSS |
| Banco | PostgreSQL em produção; SQLite suportado para testes locais |
| Assets | Vite / Node.js 24 |
| Web server | Nginx + PHP-FPM |
| Jobs | Laravel Queue + systemd |
| Scheduler | Laravel Scheduler + systemd timer |
| TLS | Certbot / Let's Encrypt |
| SO de instalação automatizada | Ubuntu Server 24.04 LTS, ARM64 ou AMD64 |

---

# Instalação rápida - Ubuntu Server 24.04

O instalador automatizado foi pensado para quem não quer configurar PHP, PostgreSQL, Nginx e systemd manualmente.

## 1. Crie uma VM

Recomendação mínima:

- Ubuntu Server **24.04 LTS**;
- 2 vCPUs;
- 2 GB RAM (4 GB recomendado);
- 20 GB de disco;
- arquitetura `amd64` ou `arm64`;
- acesso com `sudo`;
- IP público.

Oracle Cloud Ampere A1/ARM64 é compatível.

## 2. Crie o DNS

Crie um registro **A** no seu provedor DNS:

```text
Tipo: A
Nome: louvor
Destino: IP_PUBLICO_DA_VM
```

Exemplo de domínio resultante:

```text
louvor.minhaigreja.org.br
```

Aguarde o DNS resolver para o IP da VM.

## 3. Libere as portas

Na firewall/security list/NSG da sua nuvem, permita entrada TCP:

```text
22   SSH
80   HTTP / validação Let's Encrypt
443  HTTPS
```

**Não exponha PostgreSQL/5432 à Internet.**

Em algumas imagens de nuvem, especialmente Oracle Cloud, também pode existir uma regra local de `iptables` bloqueando HTTP/HTTPS. Consulte [INSTALL.md](INSTALL.md#firewall-local-e-oracle-cloud) antes de alterar regras de firewall.

## 4. Conecte por SSH

```bash
ssh ubuntu@IP_PUBLICO_DA_VM
```

Confirme o sistema:

```bash
cat /etc/os-release
uname -m
```

## 5. Instale Git e clone o projeto

```bash
sudo apt update
sudo apt install -y git
cd /home/ubuntu

git clone https://github.com/SEU_USUARIO/ourcloud-louvor.git
cd ourcloud-louvor
```

> Depois que o projeto estiver publicado, substitua `SEU_USUARIO` pelo usuário ou organização que hospeda o repositório.

## 6. Execute o instalador

Troque domínio, e-mail e nome da igreja:

```bash
sudo bash deploy/install.sh \
  --domain louvor.minhaigreja.org.br \
  --admin-email admin@minhaigreja.org.br \
  --organization "Minha Igreja"
```

O instalador cria uma senha administrativa segura automaticamente. Os segredos iniciais são gravados temporariamente em:

```text
/root/sgml-install-secrets.txt
```

Veja o arquivo apenas no servidor:

```bash
sudo cat /root/sgml-install-secrets.txt
```

Depois de guardar a senha em local seguro:

```bash
sudo shred -u /root/sgml-install-secrets.txt
```

## 7. O instalador faz automaticamente

Ele prepara:

1. PHP 8.3 e extensões;
2. PostgreSQL;
3. Nginx;
4. Composer;
5. Node.js 24 para build;
6. usuário de serviço `sgml`;
7. banco e credenciais aleatórias;
8. `.env` de produção protegido;
9. migrations;
10. dados estruturais iniciais;
11. administrador inicial;
12. PHP-FPM dedicado;
13. worker de fila;
14. scheduler;
15. diretórios protegidos de backup/restauração;
16. build de CSS/JavaScript;
17. validação Blade;
18. Nginx;
19. tentativa de emissão do HTTPS;
20. healthcheck final.

A aplicação é instalada em:

```text
/var/www/sgml
```

## 8. Valide a instalação

```bash
sudo bash /var/www/sgml/scripts/verify-install.sh
```

O resultado esperado é:

```text
VALIDAÇÃO TÉCNICA: APROVADA
```

## 9. Abra no navegador

```text
https://louvor.minhaigreja.org.br
```

Se o certificado ainda não tiver sido emitido, consulte a seção de HTTPS abaixo.

---

# HTTPS depois da instalação

Se você usou `--skip-https` ou o DNS ainda não estava pronto durante a instalação:

1. confirme que o domínio aponta para a VM;
2. confirme que portas 80 e 443 estão abertas;
3. execute:

```bash
cd /var/www/sgml

sudo bash deploy/enable-https-letsencrypt.sh \
  --domain louvor.minhaigreja.org.br \
  --email admin@minhaigreja.org.br
```

Valide:

```bash
curl -I https://louvor.minhaigreja.org.br/login
```

---

# Instalação a partir de uma Release ZIP

Se preferir não usar Git:

```bash
sudo apt update
sudo apt install -y unzip
cd /home/ubuntu
unzip OurCloud-Louvor-v1.0.0-rc88-GitHub.zip
cd ourcloud-louvor
```

Depois execute o mesmo `deploy/install.sh` mostrado acima.

---

# Desenvolvimento local

Para desenvolvimento, use PHP 8.3, Composer e Node.js 24.

```bash
git clone https://github.com/SEU_USUARIO/ourcloud-louvor.git
cd ourcloud-louvor

cp .env.example .env
composer install
php artisan key:generate

mkdir -p database
touch database/database.sqlite

npm ci
npm run build

php artisan migrate
php artisan serve
```

Acesse:

```text
http://127.0.0.1:8000
```

Para executar os testes:

```bash
composer test
```

---

# Atualização

Baixe/clone a nova versão e execute **o atualizador da nova versão**, não o da instalação antiga:

```bash
cd /caminho/da/nova-versao
sudo bash deploy/update.sh
```

O atualizador preserva `.env`, APP_KEY, banco, `storage` e arquivos enviados. Ele prepara Composer e frontend em staging antes de entrar em manutenção.

Leia [UPDATE.md](UPDATE.md) antes de atualizar produção.

---

# Migração para outra VM

Para migrar uma instalação existente, preserve:

- dump PostgreSQL;
- **APP_KEY original**;
- `storage/app`, quando houver arquivos enviados;
- credenciais de integrações necessárias.

Leia [MIGRATION.md](MIGRATION.md).

> Atenção: ao restaurar um dump, o servidor PostgreSQL de destino não deve ser uma major **mais antiga** que a origem. Veja as orientações no guia de migração.

---

# Backup

O administrador pode usar **Administração → Backup e restauração**.

No servidor, o diretório padrão é:

```text
/var/backups/sgml/database
```

Backups contêm dados pessoais e **nunca devem ser enviados ao GitHub**.

---

# Importação de louvores

O projeto público não distribui o repertório real de nenhuma igreja. A interface inclui:

- um pequeno catálogo **fictício** apenas para demonstração;
- importação CSV para o repertório real;
- modelo em `docs/catalogo_louvores_exemplo.csv`.

O sistema não inclui letras de músicas protegidas por direitos autorais.

---

# Segurança e privacidade

Nunca publique:

```text
.env
APP_KEY
senha do PostgreSQL
*.dump
*.sql
sgml-install-secrets.txt
sgml-app-key.txt
tokens Google/WhatsApp/SMTP
chaves privadas
logs de produção
dados reais de membros
```

Veja [SECURITY.md](SECURITY.md) e [PRIVACY.md](PRIVACY.md).

Se encontrar uma vulnerabilidade, **não abra um Issue público com detalhes exploráveis**. Use o recurso privado **Security → Advisories → Report a vulnerability** do GitHub, quando habilitado pelo mantenedor.

---

# Documentação

- [INSTALL.md](INSTALL.md) - instalação detalhada para iniciantes;
- [UPDATE.md](UPDATE.md) - atualização segura;
- [MIGRATION.md](MIGRATION.md) - mudança/restauração de VM;
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - problemas comuns;
- [WHATSAPP.md](WHATSAPP.md) - provedores WhatsApp;
- [SECURITY.md](SECURITY.md) - segurança;
- [PRIVACY.md](PRIVACY.md) - dados pessoais e cuidados operacionais;
- [CONTRIBUTING.md](CONTRIBUTING.md) - como contribuir;
- [CHANGELOG.md](CHANGELOG.md) - histórico de versões;
- [PUBLISHING.md](PUBLISHING.md) - primeira publicação no GitHub.

---

# Como contribuir

Contribuições são bem-vindas. Um fluxo simples:

1. faça um fork;
2. crie uma branch a partir de `main`;
3. faça uma alteração pequena e focada;
4. adicione/atualize testes;
5. execute `composer test` e `npm run build`;
6. abra um Pull Request explicando problema, solução e impactos.

Leia [CONTRIBUTING.md](CONTRIBUTING.md).

---

# Licença

OurCloud Louvor é distribuído sob a **GNU Affero General Public License v3.0 - AGPL-3.0-only**.

Em resumo, você pode usar, estudar, modificar e redistribuir o software, respeitando os termos da AGPL. Se disponibilizar uma versão modificada como serviço pela rede, as obrigações de disponibilização do código-fonte previstas pela AGPL também se aplicam.

O texto completo está em [LICENSE](LICENSE).

---

# Aviso

Este projeto é fornecido **sem garantia**, nos termos da licença. Cada organização é responsável por sua infraestrutura, backups, segurança, bases legais, políticas de privacidade, tratamento de dados pessoais, integrações e conteúdo cadastrado.
