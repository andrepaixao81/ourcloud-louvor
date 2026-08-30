# Privacidade e dados pessoais

O OurCloud Louvor pode armazenar dados pessoais de membros e equipes, como nome, e-mail, telefone, data de nascimento, disponibilidade, participação ministerial e histórico operacional.

## Responsabilidade da organização que instala

O software é uma ferramenta. Quem opera uma instalação é responsável por definir e cumprir:

- base legal aplicável;
- política de privacidade;
- perfis de acesso;
- retenção e descarte;
- segurança de backups;
- gestão de incidentes;
- contratos e integrações de terceiros;
- atendimento a direitos dos titulares quando aplicável.

## Boas práticas

- use HTTPS;
- restrinja SSH;
- não exponha PostgreSQL à Internet;
- mantenha Ubuntu e dependências atualizados;
- revise papéis/permissões;
- faça backups protegidos;
- não use banco de produção em desenvolvimento;
- não envie dumps a Issues/Pull Requests;
- mantenha credenciais fora do Git.

## Aniversários

A interface deve exibir aniversariantes apenas aos perfis autorizados. O ano de nascimento/idade não deve ser divulgado desnecessariamente.

## Logs e suporte

Antes de compartilhar logs em uma issue, remova:

- nomes reais;
- e-mails;
- telefones;
- tokens;
- cookies;
- IDs de sessão;
- URLs privadas;
- conteúdos de mensagens.
