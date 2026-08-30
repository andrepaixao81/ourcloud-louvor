# OurCloud Louvor v1.0.0-rc88

## GitHub / Open Source Edition

A RC88 transforma a base funcional da RC87 em uma distribuição preparada para publicação pública e colaboração no GitHub.

### Open source

- licença **GNU AGPL v3.0** adicionada em `LICENSE`;
- `composer.json` alterado para `AGPL-3.0-only`;
- README público com instalação completa;
- `.env.example` sem segredos;
- `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `PRIVACY.md` e `CHANGELOG.md`;
- templates de Issues e Pull Requests;
- workflow de GitHub Actions para PHP, frontend e testes.

### Generalização para comunidade

- catálogo embutido específico de uma organização foi removido;
- importação embutida agora usa somente registros fictícios de demonstração;
- modelo CSV foi sanitizado;
- placeholders e exemplos foram generalizados;
- nenhum repertório real de igreja é necessário para utilizar o projeto.

### Instalador

- corrigido o gerador automático da senha administrativa para não depender de um pipeline `tr | head` sob `set -o pipefail`;
- a senha automática agora usa `openssl rand -hex 16`;
- preservadas as correções de backup runtime, permissões públicas, staging, Blade e assets das versões anteriores.

### Funcionalidades preservadas

A RC88 preserva a base RC87, incluindo:

- UI/UX responsiva;
- agenda e escalas;
- disponibilidade e trocas;
- repertórios;
- ensaios;
- memória de tom por ministro + louvor;
- Central de Ações;
- 403 amigável;
- aniversariantes por perfil;
- WhatsApp;
- Google Calendar/ICS;
- backup e restauração;
- Nginx/PHP-FPM/systemd;
- instalação ARM64/AMD64.

### Banco de dados

Não há migration nova exclusiva da RC88. As migrations até a RC87 permanecem no pacote.
