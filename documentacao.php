<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'theme.php';

$theme = handleTheme($pdo);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Documentação - ProHelp Financeiro</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
        /* Fallback rápido caso theme.css não carregue no servidor */
        body {
            margin: 0;
            background: var(--page-bg, #eef3f7);
            color: var(--text-color, #1f2937);
            font-family: 'Poppins', 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 28px 32px 34px; display: flex; flex-direction: column; gap: 18px; }
        .page-header { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
        .page-title { display: flex; flex-direction: column; gap: 4px; }
        .page-title h1 { margin: 0; font-size: 28px; font-weight: 800; letter-spacing: -0.4px; }
        .eyebrow { text-transform: uppercase; letter-spacing: 0.6px; font-size: 12px; color: var(--muted-color, #4b5563); margin: 0; }
        .doc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
        .doc-card { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 14px; padding: 14px 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .doc-card h4 { margin: 0 0 6px; font-weight: 800; font-size: 16px; }
        .doc-card p { margin: 0; color: var(--muted-color, #4b5563); font-size: 13px; line-height: 1.5; }
        .doc-section { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 18px 18px 12px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .doc-section h2 { margin: 0 0 10px; font-size: 20px; font-weight: 800; letter-spacing: -0.2px; }
        .doc-section p { margin: 0 0 10px; color: var(--muted-color, #4b5563); }
        .doc-section ul { padding-left: 18px; margin: 0 0 10px; color: var(--text-color, #1f2937); }
        .doc-section li { margin-bottom: 6px; line-height: 1.45; }
        .label { display: inline-block; padding: 4px 8px; border-radius: 10px; background: var(--surface-soft, #f1f4f8); font-weight: 700; font-size: 12px; margin-right: 6px; }
        .anchor-list { display: flex; flex-wrap: wrap; gap: 10px; padding: 0; margin: 8px 0 0; list-style: none; }
        .anchor-list a { text-decoration: none; border: 1px solid var(--border-color, #d9e1eb); border-radius: 12px; padding: 8px 10px; color: var(--text-color, #1f2937); background: var(--surface-soft, #f1f4f8); font-weight: 700; }
        .anchor-list a:hover { background: var(--surface-color, #f9fbfd); }
        .button { border: none; border-radius: 12px; padding: 11px 16px; font-weight: 800; cursor: pointer; letter-spacing: 0.2px; transition: transform 0.08s ease, box-shadow 0.16s ease, background 0.12s ease, color 0.12s ease; }
        .button-outline { background: var(--surface-soft, #f1f4f8); color: var(--text-color, #1f2937); border: 1px solid var(--border-color, #d9e1eb); }
        .button-primary { background: linear-gradient(135deg, #10b981, #0ea5e9); color: #fff; box-shadow: 0 14px 24px rgba(16,185,129,0.25); }
        .button:hover { transform: translateY(-1px); }
        .section-footnote { font-size: 12px; color: var(--muted-color, #4b5563); margin-top: 6px; }
        .panel { background: var(--surface-color, #f9fbfd); border: 1px solid var(--border-color, #d9e1eb); border-radius: 16px; padding: 16px; box-shadow: var(--shadow-soft, 0 4px 10px rgba(0,0,0,0.06)); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 12px; background: var(--surface-soft, #f1f4f8); font-weight: 600; gap: 6px; }
        @media (max-width: 700px) {
            .content { padding: 18px 16px 22px; }
            .doc-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="layout" id="top">
        <?php renderSidebar('ajuda'); ?>
        <main class="content">
            <div class="page-header">
                <div class="page-title">
                    <p class="eyebrow">Ajuda</p>
                    <h1>Documentação do sistema</h1>
                    <span class="text-muted">Guia rápido para operar o ProHelp Financeiro</span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="button button-outline" onclick="window.location.hash = '#top';">Voltar ao topo</button>
                    <button type="button" class="button button-primary" onclick="window.print()">Imprimir / salvar PDF</button>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title mb-0">Atalhos desta página</h3>
                    <span class="pill">Use os links para ir direto ao assunto</span>
                </div>
                <ul class="anchor-list">
                    <li><a href="#visao-geral">Visão geral</a></li>
                    <li><a href="#acesso-perfis">Acesso e perfis</a></li>
                    <li><a href="#navegacao">Navegação</a></li>
                    <li><a href="#despesas">Lançar e consultar despesas</a></li>
                    <li><a href="#planejamento">Planejamento e categorias</a></li>
                    <li><a href="#dashboards">Dashboards</a></li>
                    <li><a href="#administrativo">Funções administrativas</a></li>
                    <li><a href="#interface">Interface e tema</a></li>
                    <li><a href="#seguranca">Segurança e boas práticas</a></li>
                </ul>
            </div>

            <div class="doc-grid">
                <div class="doc-card">
                    <h4>O que é</h4>
                    <p>Painel web para controlar despesas, acompanhar dashboards e registrar orçamentos mensais.</p>
                </div>
                <div class="doc-card">
                    <h4>Principais telas</h4>
                    <p>Principal, Dashboards (anual e mensal), Despesas, Orçamentos, Categorias, Regras de categoria e Aprovação de usuários.</p>
                </div>
                <div class="doc-card">
                    <h4>Recursos-chave</h4>
                    <p>Lançamento de despesas recorrentes/parceladas, filtros rápidos, marcação de pagamento, orçamento por categoria e tema claro/escuro salvo por usuário.</p>
                </div>
            </div>

            <section class="doc-section" id="visao-geral">
                <h2>1. Visão geral do sistema</h2>
                <p>O ProHelp Financeiro centraliza o lançamento de despesas, análise gráfica e acompanhamento de limites por categoria.</p>
                <ul>
                    <li><span class="label">Página Principal</span> resumo rápido com atalhos para dashboards e novos lançamentos.</li>
                    <li><span class="label">Tema</span> claro/escuro aplicável em todas as telas (botão na barra lateral).</li>
                    <li><span class="label">Ajuda</span> botão “Ajuda” na lateral abre esta documentação a qualquer momento.</li>
                </ul>
            </section>

            <section class="doc-section" id="acesso-perfis">
                <h2>2. Acesso e perfis</h2>
                <ul>
                    <li>Login com usuário e senha cadastrados em <code>usuarios</code>. A sessão é exigida para acessar todas as páginas internas.</li>
                    <li>Novos usuários ficam com status pendente até aprovação de um administrador.</li>
                    <li>Administradores possuem acesso adicional à tela de <strong>Aprovar usuários</strong> e conseguem liberar novos logins.</li>
                </ul>
                <p class="section-footnote">Se encontrar “Acesso negado”, confirme com um administrador se o seu usuário foi aprovado e tem a permissão correta.</p>
            </section>

            <section class="doc-section" id="navegacao">
                <h2>3. Navegação rápida</h2>
                <ul>
                    <li><span class="label">Principal</span> boas-vindas, atalho para dashboards e aviso de despesas próximas ao vencimento.</li>
                    <li><span class="label">Dashboards</span> visão anual (<code>dashboard.php</code>) e mensal (<code>dashboard_mensal.php</code>) com gráficos de status, categorias, recorrência e variação.</li>
                    <li><span class="label">Despesas</span> listagem, filtros e ações de edição/pagamento.</li>
                    <li><span class="label">Orçamentos</span> registrar limite mensal por categoria.</li>
                    <li><span class="label">Categorias</span> criar ou ajustar categorias para organizar lançamentos.</li>
                    <li><span class="label">Regras de categoria</span> configurar textos-chave que classificam automaticamente novas despesas.</li>
                </ul>
            </section>

            <section class="doc-section" id="despesas">
                <h2>4. Lançar e consultar despesas</h2>
                <ul>
                    <li><strong>Cadastro</strong>: use “Nova despesa” para informar descrição, datas de vencimento/pagamento, valor, juros, forma de pagamento, local e observações.</li>
                    <li><strong>Status</strong>: selecione Pago, Pendente ou Previsto. Na listagem, o botão “Marcar pago” já atualiza o status e cria a próxima previsão para lançamentos recorrentes.</li>
                    <li><strong>Recorrência/parcelas</strong>: marque “Recorrente” para gerar automaticamente o próximo mês quando o pagamento é confirmado. Use “Parcelado” para registrar número e total de parcelas.</li>
                    <li><strong>Filtros</strong>: na tela de despesas filtre por status, categoria, intervalo de vencimento, recorrência ou parcelamento. O botão “Imprimir” gera uma versão enxuta para PDF.</li>
                    <li><strong>Edição e exclusão</strong>: cada linha permite editar ou remover o lançamento. Exclusões pedem confirmação antes de serem efetivadas.</li>
                </ul>
            </section>

            <section class="doc-section" id="planejamento">
                <h2>5. Planejamento e categorias</h2>
                <ul>
                    <li><strong>Orçamentos</strong>: defina um limite para categoria + mês. O sistema impede duplicidade para o mesmo par categoria/mês.</li>
                    <li><strong>Categorias</strong>: cadastre nomes usados nos filtros e dashboards. Podem ser criadas rapidamente no ato do lançamento de despesas.</li>
                    <li><strong>Regras de categoria</strong>: associe uma palavra-chave a uma categoria para que novas despesas sejam classificadas automaticamente quando a descrição contiver o termo.</li>
                </ul>
            </section>

            <section class="doc-section" id="dashboards">
                <h2>6. Dashboards</h2>
                <ul>
                    <li><strong>Dashboard anual</strong>: totala pagamentos, pendências e previstos por ano e traz gráficos de categoria, mês, recorrência e variação entre anos.</li>
                    <li><strong>Dashboard mensal</strong>: foco no intervalo de um mês, com distribuição por dia, categoria, status, recorrência e comparação com o mês anterior.</li>
                    <li><strong>Filtros</strong>: selecione o ano ou o mês no topo de cada dashboard; use os botões de atalho para voltar à listagem de despesas.</li>
                </ul>
            </section>

            <section class="doc-section" id="administrativo">
                <h2>7. Funções administrativas</h2>
                <ul>
                    <li><strong>Aprovar usuários</strong>: disponível apenas para administradores. Mostra todos os cadastros pendentes e libera o acesso com um clique.</li>
                    <li><strong>Temas e sessão</strong>: a escolha de tema fica salva no perfil e é aplicada em toda a navegação.</li>
                </ul>
            </section>

            <section class="doc-section" id="interface">
                <h2>8. Interface e tema</h2>
                <ul>
                    <li><span class="label">Tema</span> botão na barra lateral alterna entre claro/escuro e salva a preferência no banco.</li>
                    <li><span class="label">Impressão</span> alguns relatórios têm botão “Imprimir” que remove elementos de ação para facilitar PDF.</li>
                    <li><span class="label">Pop-ups</span> mensagens de sucesso/erro aparecem no canto inferior direito e somem automaticamente.</li>
                </ul>
            </section>

            <section class="doc-section" id="seguranca">
                <h2>9. Segurança e boas práticas</h2>
                <ul>
                    <li>Saia pelo botão “Sair” na barra lateral para encerrar a sessão com segurança.</li>
                    <li>Evite compartilhar usuários; use um login por pessoa e solicite aprovação ao administrador quando necessário.</li>
                    <li>Mantenha o navegador atualizado e utilize conexão segura ao acessar o sistema.</li>
                    <li>Antes de exclusões, confirme se o lançamento não será necessário em relatórios históricos.</li>
                </ul>
                <p class="section-footnote">Precisa de algo mais? Abra o botão “Ajuda” na barra lateral ou fale com o responsável pelo sistema para suporte.</p>
            </section>
        </main>
    </div>
</body>
</html>
