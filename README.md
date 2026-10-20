# LibreForge

Tipos de post, campos personalizados, páginas de opções, consultas, templates e listagens dinâmicas para Gutenberg e Elementor, sem código.

Precisa dos plugins **libreblocks-core** ativos.

- WordPress 6.6+ e PHP 8.0+
- Gutenberg e Elementor
- Licença: GPL-2.0-or-later (veja [LICENSE](LICENSE))
- Versão atual: 0.8.7

*Post types, custom fields, options pages, queries, item templates and dynamic listings for Gutenberg and Elementor, without code.*

## Instalação

Baixe o zip pronto em [libreblocks.com.br](https://libreblocks.com.br) (ou no diretório do WordPress.org) e envie em **Plugins → Adicionar novo → Enviar plugin**.

## Desenvolvimento

Os plugins LibreBlocks são desenvolvidos lado a lado. Clone os repositórios na mesma pasta, com estes nomes:

```bash
git clone https://github.com/USUARIO/libreblocks-core.git
git clone https://github.com/USUARIO/libreforge.git
```

Depois, em `libreblocks-core/`, rode `npm install` e crie um link de `node_modules` em `libreforge/`. Então:

- `npm run build` gera os arquivos em `build/`
- `phpunit` roda os testes de `tests/Unit` (sem banco de dados)
