# Avisos pelo WhatsApp

O SGML possui uma camada de avisos internos e uma integração opcional com WhatsApp. Os avisos internos continuam funcionando mesmo quando nenhum provedor de WhatsApp estiver configurado.

## Provedores disponíveis

Em **Administração > Avisos pelo WhatsApp**, o Administrador pode escolher:

- **Evolution API** - indicada para instalações que já utilizam uma instância Evolution API;
- **Meta WhatsApp Cloud API** - integração oficial da Meta já suportada pelo SGML.

A seleção é feita por organização e pode ser alterada posteriormente. As credenciais de um provedor não são apagadas ao trocar para o outro.

## Evolution API

Antes de configurar o SGML, tenha uma instância Evolution API criada e conectada.

Informe:

- **URL da Evolution API** - por exemplo `https://whatsapp.exemplo.com.br`;
- **Instância** - o nome exato da instância;
- **API Key**;
- **Atraso em milissegundos** - valor opcional aplicado ao envio;
- **Prévia de links** - solicita a geração de preview quando suportada.

Não inclua `/manager`, `/message/sendText` ou o nome da instância na URL base.

O SGML envia mensagens de texto para:

```text
POST {URL_BASE}/message/sendText/{INSTANCIA}
```

usando o cabeçalho `apikey` e um corpo equivalente a:

```json
{
  "number": "5521999999999",
  "text": "Mensagem do SGML",
  "delay": 500,
  "linkPreview": true
}
```

### Testar

1. salve a configuração;
2. clique em **Testar conexão**;
3. informe um telefone em **Enviar mensagem de teste**;
4. consulte **Últimos envios**.

O teste de mensagem é a validação mais importante, porque confirma URL, instância, chave, fila e envio de ponta a ponta.

## Meta WhatsApp Cloud API

Informe:

- versão Graph API;
- Phone Number ID;
- Access Token;
- nome do template aprovado;
- idioma do template.

O template utilizado pelo SGML deve aceitar três variáveis no corpo: título, resumo e link.

## Fila

O SGML não espera o provedor responder durante a publicação de uma escala ou alteração de um evento. A aplicação cria um registro de entrega e envia pela fila `sgml-queue.service`.

Se o provedor estiver temporariamente indisponível, a operação principal do SGML não é desfeita. O histórico de envio registra falhas e a fila pode tentar novamente conforme a política do job.

## Telefones

Para números brasileiros, o SGML aceita telefone local com DDD (10 ou 11 dígitos) e acrescenta o DDI `55`. Também aceita números já informados em formato internacional somente com dígitos.

Exemplo:

```text
21 99999-9999 -> 5521999999999
```

## Segurança

- API Keys e Access Tokens são armazenados criptografados no banco;
- os segredos já salvos não são reexibidos na interface;
- somente Administradores podem alterar a integração;
- não publique credenciais no GitHub ou em arquivos `.env` versionados;
- prefira HTTPS para a URL da Evolution API quando ela estiver fora da própria VM/rede segura.

## Formato dos avisos de repertório

Ao confirmar um repertório, o aviso usa linhas separadas para facilitar a leitura no celular. O resumo inclui ministro, dia da semana, data, horário, abertura, louvores numerados e respectivos tons.

## Aviso de escala

Na tela **Escalas**, o Administrador ou a liderança escolhe, em cada publicação, se deseja enviar o aviso pelo WhatsApp usando o checkbox **Enviar aviso da escala pelo WhatsApp**. A opção está ligada à publicação oficial da escala, e não à sugestão em revisão.

## Telefone dos usuários

O destinatário pode manter o próprio número em **Minha conta → Telefone / WhatsApp**. Informe DDD + número, por exemplo `(21) 99999-9999`. O SGML normaliza o valor e acrescenta o DDI `55` no momento do envio para números brasileiros.

A Administração continua podendo informar o telefone pelo cadastro de Pessoas; ambos editam o mesmo campo `people.phone`.

## Felicitações de aniversário

A RC81 pode enviar automaticamente uma mensagem no dia do aniversário usando o mesmo provedor WhatsApp configurado para a organização. A mensagem e o horário são configurados em **Aniversariantes** por Administrador ou Responsável pelo Ministério. O sistema usa o telefone cadastrado na Pessoa, evita envio duplicado no mesmo dia e não inclui idade ou ano de nascimento na lista de aniversariantes.
