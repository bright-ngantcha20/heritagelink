<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$user       = currentUser();
$hasProfile = hasProfile($pdo, $user['id']);

if (!$hasProfile) {
    redirect(SITE_URL . '/settings/profile.php');
}

$myMember = getUserMember($pdo, $user['id']);
?>
<?php require_once '../includes/header.php'; ?>

<style>
* { box-sizing: border-box; }

#tree-page {
    width: 100%;
    height: calc(100vh - 70px);
    background: #0a0a18;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

#tree-toolbar {
    position: absolute;
    top: 1rem;
    left: 1rem;
    display: flex;
    gap: 0.5rem;
    align-items: center;
    z-index: 100;
    flex-wrap: wrap;
}

.toolbar-btn {
    background: rgba(17,17,39,0.92);
    border: 1px solid #1e1e3a;
    color: #aaa;
    padding: 0.45rem 1rem;
    border-radius: 8px;
    font-size: 0.82rem;
    cursor: pointer;
    backdrop-filter: blur(8px);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
}

.toolbar-btn:hover {
    border-color: #00d4ff;
    color: #fff;
}

#member-count {
    background: rgba(0,212,255,0.12);
    border: 1px solid rgba(0,212,255,0.25);
    color: #00d4ff;
    padding: 0.45rem 1rem;
    border-radius: 8px;
    font-size: 0.82rem;
}

#tree-svg {
    width: 100%;
    height: 100%;
    cursor: grab;
}

#tree-svg:active { cursor: grabbing; }

/* Node card */
.node-card {
    cursor: pointer;
    transition: all 0.2s;
}

.node-card:hover .card-bg {
    filter: brightness(1.25);
}

/* Links */
.link-parent {
    stroke: #4a90d9;
    stroke-width: 2;
    fill: none;
    opacity: 0.6;
}

.link-spouse {
    stroke: #d94a8a;
    stroke-width: 1.5;
    fill: none;
    stroke-dasharray: 6,3;
    opacity: 0.5;
}

.link-sibling {
    stroke: #4ad9a0;
    stroke-width: 1.5;
    fill: none;
    stroke-dasharray: 3,4;
    opacity: 0.4;
}

/* Zoom controls */
#zoom-controls {
    position: absolute;
    bottom: 1.5rem;
    right: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    z-index: 100;
}

.zoom-btn {
    width: 38px;
    height: 38px;
    background: rgba(17,17,39,0.92);
    border: 1px solid #1e1e3a;
    border-radius: 8px;
    color: #aaa;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    backdrop-filter: blur(8px);
}

.zoom-btn:hover {
    border-color: #00d4ff;
    color: #fff;
}

/* Legend */
#tree-legend {
    position: absolute;
    bottom: 1.5rem;
    left: 1rem;
    background: rgba(17,17,39,0.92);
    border: 1px solid #1e1e3a;
    border-radius: 10px;
    padding: 0.85rem 1.1rem;
    backdrop-filter: blur(8px);
    z-index: 100;
    display: none;
}

.legend-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
    color: #888;
    margin-bottom: 5px;
}

.legend-row:last-child { margin-bottom: 0; }

/* Detail panel */
#detail-panel {
    position: absolute;
    top: 0; right: -380px;
    width: 360px;
    height: 100%;
    background: #111127;
    border-left: 1px solid #1e1e3a;
    z-index: 200;
    transition: right 0.3s ease;
    overflow-y: auto;
    padding: 1.5rem;
    padding-top: 3rem;
}

#detail-panel.open { right: 0; }

#panel-close {
    position: absolute;
    top: 1rem; right: 1rem;
    background: #1e1e3a;
    border: none;
    color: #888;
    width: 32px; height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

#panel-close:hover {
    background: #2a2a4a;
    color: #fff;
}

/* Generation label */
.gen-label {
    fill: #1e1e3a;
    font-size: 11px;
    font-family: 'Segoe UI', sans-serif;
}

/* Empty state */
#empty-state {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #555;
    display: none;
}
</style>

<div id="tree-page">

    <!-- Toolbar -->
    <div id="tree-toolbar">
        <div id="member-count">Loading...</div>
        <button class="toolbar-btn"
                onclick="resetView()">
            <i class="ti ti-home"></i> Reset
        </button>
        <a href="<?= SITE_URL ?>/family/add.php"
           class="toolbar-btn">
            <i class="ti ti-user-plus"></i>
            Add Member
        </a>
        <button class="toolbar-btn"
                onclick="toggleLegend()">
            <i class="ti ti-info-circle"></i>
            Legend
        </button>
    </div>

    <!-- SVG -->
    <svg id="tree-svg">
        <g id="tree-g"></g>
    </svg>

    <!-- Zoom controls -->
    <div id="zoom-controls">
        <button class="zoom-btn"
                onclick="zoomIn()">+</button>
        <button class="zoom-btn"
                onclick="zoomOut()">−</button>
        <button class="zoom-btn"
                onclick="resetView()"
                style="font-size:0.9rem">↺</button>
    </div>

    <!-- Legend -->
    <div id="tree-legend">
        <div style="color:#aaa;font-size:0.72rem;
                    font-weight:600;
                    text-transform:uppercase;
                    letter-spacing:0.06em;
                    margin-bottom:0.6rem">
            Legend
        </div>
        <div class="legend-row">
            <div style="width:14px;height:14px;
                        border-radius:3px;
                        background:#0d1a3e;
                        border:2px solid #00d4ff">
            </div>
            You (root member)
        </div>
        <div class="legend-row">
            <div style="width:14px;height:14px;
                        border-radius:3px;
                        background:#0d1526;
                        border:2px solid #4a90d9">
            </div>
            Male member
        </div>
        <div class="legend-row">
            <div style="width:14px;height:14px;
                        border-radius:3px;
                        background:#1a0d1a;
                        border:2px solid #d94a8a">
            </div>
            Female member
        </div>
        <div class="legend-row">
            <div style="width:14px;height:14px;
                        border-radius:3px;
                        background:#111;
                        border:2px dashed #555">
            </div>
            Deceased
        </div>
        <div style="border-top:1px solid #1e1e3a;
                    margin:0.5rem 0"></div>
        <div class="legend-row">
            <div style="width:22px;height:2px;
                        background:#4a90d9"></div>
            Parent / Child
        </div>
        <div class="legend-row">
            <div style="width:22px;height:2px;
                        background:repeating-linear-gradient(
                          90deg,#d94a8a 0,
                          #d94a8a 5px,
                          transparent 5px,
                          transparent 9px)">
            </div>
            Spouse
        </div>
        <div class="legend-row">
            <div style="width:22px;height:2px;
                        background:repeating-linear-gradient(
                          90deg,#4ad9a0 0,
                          #4ad9a0 3px,
                          transparent 3px,
                          transparent 7px)">
            </div>
            Sibling
        </div>
    </div>

    <!-- Detail panel -->
    <div id="detail-panel">
        <button id="panel-close"
                onclick="closePanel()">
            <i class="ti ti-x"></i>
        </button>
        <div id="panel-content"></div>
    </div>

    <!-- Empty state -->
    <div id="empty-state">
        <i class="ti ti-git-fork"
           style="font-size:3rem;
                  display:block;
                  margin-bottom:1rem;
                  color:#1e1e3a"></i>
        <h4 style="color:#555;
                   margin-bottom:0.5rem">
            No family members yet
        </h4>
        <p style="font-size:0.9rem;
                  margin-bottom:1rem">
            Add relatives to build your tree
        </p>
        <a href="<?= SITE_URL ?>/family/add.php"
           style="background:#00d4ff;color:#000;
                  padding:0.5rem 1.25rem;
                  border-radius:8px;
                  font-weight:600;
                  text-decoration:none;
                  font-size:0.9rem">
            Add First Member
        </a>
    </div>

</div>

<!-- D3.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>

<script>
const SITE_URL = '<?= SITE_URL ?>';
const ROOT_ID  = <?= $myMember['member_id'] ?>;

// Card dimensions
const CW = 150; // card width
const CH = 72;  // card height
const GH = 180; // vertical gap between generations
const GW = 180; // horizontal gap between siblings

let svg, g, zoom, treeData;

// ── Load data ─────────────────────────────────
async function loadTree() {
    try {
        const res  = await fetch(
            SITE_URL + '/api/tree.php'
        );
        treeData = await res.json();

        if (treeData.error
            || !treeData.nodes
            || treeData.nodes.length === 0) {
            showEmpty();
            return;
        }

        document.getElementById('member-count')
            .textContent =
            treeData.counts.total + ' member' +
            (treeData.counts.total !== 1
                ? 's' : '');

        buildHierarchy(treeData);

    } catch(e) {
        console.error(e);
        showEmpty();
    }
}

function buildHierarchy(data) {
    const nodes = data.nodes;
    const links = data.links;

    // levelMap: ROOT = 0
    // Ancestors = negative numbers (going up)
    // Descendants = positive numbers (going down)
    const levelMap = {};
    levelMap[ROOT_ID] = 0;

    const visited = new Set([ROOT_ID]);
    const queue   = [ROOT_ID];

    while (queue.length) {
        const current      = queue.shift();
        const currentLevel = levelMap[current];

        links.forEach(l => {
            const src   = l.source.id ?? l.source;
            const tgt   = l.target.id ?? l.target;
            const type  = l.type;
            const label = l.label || '';

            // ── Rule 1 ────────────────────────
            // current → someone with type=parent
            // means: that someone IS the parent
            // of current → goes ONE LEVEL UP
            if (src === current
                && type === 'parent'
                && !visited.has(tgt)) {
                levelMap[tgt] = currentLevel - 1;
                visited.add(tgt);
                queue.push(tgt);
            }

            // ── Rule 2 ────────────────────────
            // current → someone with type=child
            // means: that someone IS the child
            // of current → goes ONE LEVEL DOWN
            if (src === current
                && type === 'child'
                && !visited.has(tgt)) {
                levelMap[tgt] = currentLevel + 1;
                visited.add(tgt);
                queue.push(tgt);
            }

            // ── Rule 3 ────────────────────────
            // someone → current with type=child
            // means: current IS the parent
            // of that someone
            // → that someone goes ONE LEVEL DOWN
            if (tgt === current
                && type === 'child'
                && !visited.has(src)) {
                levelMap[src] = currentLevel + 1;
                visited.add(src);
                queue.push(src);
            }

            // ── Rule 4 ────────────────────────
            // someone → current with type=parent
            // means: current IS the child
            // → that someone goes ONE LEVEL UP
            if (tgt === current
                && type === 'parent'
                && !visited.has(src)) {
                levelMap[src] = currentLevel - 1;
                visited.add(src);
                queue.push(src);
            }

            // ── Spouse → same level ───────────
            if (type === 'spouse') {
                if (src === current
                    && !visited.has(tgt)) {
                    levelMap[tgt] = currentLevel;
                    visited.add(tgt);
                    queue.push(tgt);
                }
                if (tgt === current
                    && !visited.has(src)) {
                    levelMap[src] = currentLevel;
                    visited.add(src);
                    queue.push(src);
                }
            }

            // ── Sibling → same level ──────────
            if (type === 'sibling') {
                if (src === current
                    && !visited.has(tgt)) {
                    levelMap[tgt] = currentLevel;
                    visited.add(tgt);
                    queue.push(tgt);
                }
                if (tgt === current
                    && !visited.has(src)) {
                    levelMap[src] = currentLevel;
                    visited.add(src);
                    queue.push(src);
                }
            }
        });
    }

    // Any unvisited nodes → level 0
    nodes.forEach(n => {
        if (levelMap[n.id] === undefined) {
            levelMap[n.id] = 0;
        }
    });

    // ── Extra rule for grandparents ───────────
    // If a node at level -1 also has a parent
    // relationship to another node, push that
    // node to level -2
    // This is already handled by BFS above
    // but we need to handle grandfather specifically
    // by checking the relation_label
    nodes.forEach(n => {
        const label = '';
        // Find links FROM this node
        links.forEach(l => {
            const src   = l.source.id ?? l.source;
            const tgt   = l.target.id ?? l.target;
            const lbl   = l.label || '';

            // If Brighton → 8 with label
            // grandfather_paternal
            // then 8 should be at level -2
            if (src === ROOT_ID
                && tgt === n.id
                && (lbl.includes('grandfather')
                    || lbl.includes('grandmother')
                    || lbl.includes('great_'))) {
                levelMap[n.id] = -2;
            }
        });
    });

    // Group nodes by level
    const levels = {};
    nodes.forEach(n => {
        const lv = levelMap[n.id] ?? 0;
        if (!levels[lv]) levels[lv] = [];
        levels[lv].push(n);
    });

    const sortedLevels = Object.keys(levels)
        .map(Number)
        .sort((a, b) => a - b);

    // ── Assign X Y positions ──────────────────
    const positions = {};

    sortedLevels.forEach(lv => {
        const members = levels[lv];
        const totalW  = members.length * GW;
        const startX  = -totalW / 2 + GW / 2;
        const y       = lv * GH;

        members.forEach((n, i) => {
            positions[n.id] = {
                x: startX + i * GW,
                y: y,
            };
        });
    });

    renderHierarchy(
        nodes, links, positions,
        levelMap, sortedLevels
    );
}

// ── Render ────────────────────────────────────
function renderHierarchy(
    nodes, links, positions,
    levelMap, sortedLevels
) {
    const container =
        document.getElementById('tree-page');
    const W = container.offsetWidth;
    const H = container.offsetHeight;

    svg = d3.select('#tree-svg');
    g   = d3.select('#tree-g');

    zoom = d3.zoom()
        .scaleExtent([0.15, 3])
        .on('zoom', ev => {
            g.attr('transform', ev.transform);
        });

    svg.call(zoom);

    // ── Generation label lines ────────────────
    const genLabels = {
        '-4': 'Great Great Grandparents',
        '-3': 'Great Grandparents',
        '-2': 'Grandparents',
        '-1': 'Parents',
         '0': 'Your Generation',
         '1': 'Children',
         '2': 'Grandchildren',
    };

    sortedLevels.forEach(lv => {
        const y = lv * GH;
        const label = genLabels[lv] || '';
        if (!label) return;

        g.append('line')
            .attr('x1', -2000)
            .attr('y1', y - CH/2 - 18)
            .attr('x2',  2000)
            .attr('y2', y - CH/2 - 18)
            .attr('stroke', '#1a1a2e')
            .attr('stroke-width', 1);

        g.append('text')
            .attr('x', -600)
            .attr('y', y - CH/2 - 5)
            .attr('font-size', '10px')
            .attr('font-family',
                  'Segoe UI, sans-serif')
            .attr('fill', '#2a2a4a')
            .attr('font-weight', '600')
            .attr('text-transform', 'uppercase')
            .attr('letter-spacing', '0.08em')
            .text(label.toUpperCase());
    });

    // ── Draw links first (behind nodes) ───────
    // Only draw parent-child links as curved paths
    // Spouse and sibling as straight lines
    links.forEach(l => {
        const src = l.source.id ?? l.source;
        const tgt = l.target.id ?? l.target;
        const p1  = positions[src];
        const p2  = positions[tgt];
        if (!p1 || !p2) return;

        if (l.type === 'parent') {
            // Curved path from parent to child
            const lv1 = levelMap[src];
            const lv2 = levelMap[tgt];
            if (lv1 < lv2) {
                // src is ancestor, tgt is descendant
                const mx = (p1.x + p2.x) / 2;
                const my = (p1.y + p2.y) / 2;
                g.append('path')
                    .attr('class', 'link-parent')
                    .attr('d', `
                        M ${p1.x} ${p1.y + CH/2}
                        C ${p1.x} ${my},
                          ${p2.x} ${my},
                          ${p2.x} ${p2.y - CH/2}
                    `);
            }
        } else if (l.type === 'spouse') {
            g.append('line')
                .attr('class', 'link-spouse')
                .attr('x1', p1.x + CW/2)
                .attr('y1', p1.y)
                .attr('x2', p2.x - CW/2)
                .attr('y2', p2.y);
        } else if (l.type === 'sibling') {
            // Only draw once
            if (src < tgt) {
                g.append('line')
                    .attr('class', 'link-sibling')
                    .attr('x1', p1.x + CW/2)
                    .attr('y1', p1.y)
                    .attr('x2', p2.x - CW/2)
                    .attr('y2', p2.y);
            }
        }
    });

    // ── Draw nodes ────────────────────────────
    nodes.forEach(n => {
        const pos = positions[n.id];
        if (!pos) return;

        const isRoot = n.id === ROOT_ID;
        const ng = g.append('g')
            .attr('class', 'node-card')
            .attr('transform',
                `translate(${pos.x},${pos.y})`)
            .on('click', () => showDetail(n));

        // Card background
        ng.append('rect')
            .attr('class', 'card-bg')
            .attr('x',  -CW/2)
            .attr('y',  -CH/2)
            .attr('width',  CW)
            .attr('height', CH)
            .attr('rx', 10)
            .attr('ry', 10)
            .attr('fill', () => {
                if (isRoot)
                    return '#0d1a3e';
                if (n.is_deceased)
                    return '#0f0f0f';
                if (n.gender === 'female')
                    return '#1a0d1a';
                return '#0d1526';
            })
            .attr('stroke', () => {
                if (isRoot)
                    return '#00d4ff';
                if (n.is_deceased)
                    return '#444';
                if (n.gender === 'female')
                    return '#d94a8a';
                return '#4a90d9';
            })
            .attr('stroke-width',
                isRoot ? 2.5 : 1.5)
            .attr('stroke-dasharray',
                n.is_deceased ? '5,3' : 'none');

        // Avatar circle
        const avatarColor = isRoot
            ? '#00d4ff'
            : n.is_deceased
                ? '#444'
                : n.gender === 'female'
                    ? '#d94a8a'
                    : '#4a90d9';

        ng.append('circle')
            .attr('cx', -CW/2 + 24)
            .attr('cy', 0)
            .attr('r',  16)
            .attr('fill', () => {
                if (isRoot) return '#1a2a5e';
                if (n.is_deceased) return '#1a1a1a';
                if (n.gender === 'female')
                    return '#2e0d2e';
                return '#0d1e3a';
            })
            .attr('stroke', avatarColor)
            .attr('stroke-width', 1.5);

        // Initial
        ng.append('text')
            .attr('x', -CW/2 + 24)
            .attr('y', 5)
            .attr('text-anchor', 'middle')
            .attr('font-size', '12px')
            .attr('font-weight', '700')
            .attr('font-family',
                  'Segoe UI, sans-serif')
            .attr('fill', avatarColor)
            .text(n.name.charAt(0).toUpperCase());

        // Name
        const displayName =
            n.preferred_name || n.name;
        const truncName = displayName.length > 12
            ? displayName.substring(0, 11) + '…'
            : displayName;

        ng.append('text')
            .attr('x', -CW/2 + 47)
            .attr('y', n.date_range ? -10 : 5)
            .attr('font-size', '12px')
            .attr('font-weight', '600')
            .attr('font-family',
                  'Segoe UI, sans-serif')
            .attr('fill', isRoot ? '#fff' : '#ddd')
            .text(truncName);

        // Date range
        if (n.date_range) {
            ng.append('text')
                .attr('x', -CW/2 + 47)
                .attr('y', 5)
                .attr('font-size', '9px')
                .attr('font-family',
                      'Segoe UI, sans-serif')
                .attr('fill', '#666')
                .text(n.date_range);
        }

        // Quarter
        ng.append('text')
            .attr('x', -CW/2 + 47)
            .attr('y', n.date_range ? 19 : 18)
            .attr('font-size', '8.5px')
            .attr('font-family',
                  'Segoe UI, sans-serif')
            .attr('fill',
                n.quarter_id ? '#00d4ff' : '#666')
            .attr('opacity', 0.8)
            .text(n.quarter
                ? n.quarter.substring(0, 14)
                : '');

        // YOU badge
        if (isRoot) {
            ng.append('rect')
                .attr('x',  CW/2 - 34)
                .attr('y', -CH/2 + 3)
                .attr('width',  30)
                .attr('height', 15)
                .attr('rx', 4)
                .attr('fill', '#00d4ff');

            ng.append('text')
                .attr('x', CW/2 - 19)
                .attr('y', -CH/2 + 13)
                .attr('text-anchor', 'middle')
                .attr('font-size', '8px')
                .attr('font-weight', '800')
                .attr('font-family',
                      'Segoe UI, sans-serif')
                .attr('fill', '#000')
                .text('YOU');
        }

        // Verified badge
        if (n.verified && !isRoot) {
            ng.append('circle')
                .attr('cx', CW/2 - 8)
                .attr('cy', -CH/2 + 8)
                .attr('r',  7)
                .attr('fill', '#00d4ff');

            ng.append('text')
                .attr('x', CW/2 - 8)
                .attr('y', -CH/2 + 12)
                .attr('text-anchor', 'middle')
                .attr('font-size', '8px')
                .attr('font-weight', '700')
                .attr('fill', '#000')
                .text('✓');
        }

        // Deceased cross
        if (n.is_deceased) {
            ng.append('text')
                .attr('x',  CW/2 - 8)
                .attr('y',  CH/2 - 4)
                .attr('text-anchor', 'middle')
                .attr('font-size', '10px')
                .attr('fill', '#555')
                .text('†');
        }
    });

    // ── Click background to close panel ───────
    svg.on('click', () => closePanel());

    // ── Initial center view ───────────────────
    setTimeout(() => resetView(), 100);
}

// ── Detail panel ──────────────────────────────
function showDetail(n) {
    const isRoot = n.id === ROOT_ID;
    const gc = n.gender === 'female'
        ? '#d94a8a'
        : isRoot ? '#00d4ff' : '#4a90d9';

    document.getElementById('panel-content')
        .innerHTML = `

        <div style="text-align:center;
                    margin-bottom:1.5rem">
            <div style="
                width:60px;height:60px;
                border-radius:50%;
                background:${
                    n.gender === 'female'
                    ? '#2e0d2e' : '#0d1e3a'
                };
                border:2px solid ${gc};
                ${n.is_deceased
                    ? 'border-style:dashed;' : ''}
                display:flex;align-items:center;
                justify-content:center;
                font-size:1.4rem;font-weight:700;
                color:${gc};
                margin:0 auto 0.75rem;
            ">
                ${n.name.charAt(0).toUpperCase()}
            </div>
            <h4 style="color:#fff;font-size:1rem;
                       font-weight:600;
                       margin-bottom:3px">
                ${esc(n.name)}
            </h4>
            ${n.preferred_name ? `
            <div style="color:#888;
                        font-size:0.82rem;
                        margin-bottom:5px">
                "${esc(n.preferred_name)}"
            </div>` : ''}
            <div style="
                display:inline-block;
                background:rgba(0,212,255,0.1);
                border:1px solid rgba(0,212,255,0.2);
                color:#00d4ff;font-size:0.72rem;
                padding:2px 10px;border-radius:20px;
            ">
                ${esc(n.quarter || 'Unknown')}
            </div>
            ${n.is_deceased ? `
            <div style="color:#555;
                        font-size:0.78rem;
                        margin-top:5px">
                † Deceased
            </div>` : ''}
            ${n.verified ? `
            <div style="color:#00d4ff;
                        font-size:0.75rem;
                        margin-top:3px">
                ✓ Verified record
            </div>` : ''}
        </div>

        <div style="
            background:#0d0d1a;
            border:1px solid #1e1e3a;
            border-radius:10px;
            padding:0.85rem 1rem;
            margin-bottom:1rem;
        ">
            ${n.date_range ? `
            <div style="display:flex;gap:8px;
                        margin-bottom:0.5rem">
                <span style="color:#555;
                             font-size:0.82rem">
                    📅
                </span>
                <span style="color:#aaa;
                             font-size:0.82rem">
                    ${esc(n.date_range)}
                </span>
            </div>` : ''}
            ${n.birthplace ? `
            <div style="display:flex;gap:8px;
                        margin-bottom:0.5rem">
                <span style="color:#555;
                             font-size:0.82rem">
                    📍
                </span>
                <span style="color:#aaa;
                             font-size:0.82rem">
                    ${esc(n.birthplace)}
                </span>
            </div>` : ''}
            ${n.occupation ? `
            <div style="display:flex;gap:8px">
                <span style="color:#555;
                             font-size:0.82rem">
                    💼
                </span>
                <span style="color:#aaa;
                             font-size:0.82rem">
                    ${esc(n.occupation)}
                </span>
            </div>` : ''}
            ${!n.date_range
              && !n.birthplace
              && !n.occupation ? `
            <div style="color:#555;
                        font-size:0.82rem;
                        font-style:italic">
                No additional details recorded
            </div>` : ''}
        </div>

        ${n.bio ? `
        <div style="
            color:#888;font-size:0.82rem;
            line-height:1.6;
            padding:0.75rem;
            border-left:2px solid #1e1e3a;
            margin-bottom:1rem;
        ">
            ${esc(n.bio)}
        </div>` : ''}

        <div style="
            display:flex;
            flex-direction:column;
            gap:0.5rem;
            margin-top:1rem;
        ">
            ${!isRoot ? `
            <button onclick="openChat(
                ${n.id}, '${esc(n.name)}')"
                style="
                    background:#00d4ff;
                    border:none;color:#000;
                    padding:0.65rem;
                    border-radius:8px;
                    font-size:0.85rem;
                    font-weight:600;
                    cursor:pointer;
                    width:100%;
                ">
                💬 Send Message
            </button>` : `
            <div style="
                background:rgba(0,212,255,0.06);
                border:1px solid rgba(0,212,255,0.15);
                border-radius:8px;
                padding:0.65rem;
                text-align:center;
                color:#00d4ff;
                font-size:0.82rem;
            ">
                This is your profile node
            </div>`}
            <a href="${SITE_URL}/family/add.php"
               style="
                    background:#1e1e3a;
                    border:1px solid #2a2a4a;
                    color:#aaa;padding:0.65rem;
                    border-radius:8px;
                    font-size:0.85rem;
                    display:block;
                    text-align:center;
                    text-decoration:none;
               ">
                + Add Their Relative
            </a>
        </div>
    `;

    document.getElementById('detail-panel')
        .classList.add('open');
}

function closePanel() {
    document.getElementById('detail-panel')
        .classList.remove('open');
}

// ── Chat ──────────────────────────────────────
function openChat(userId, userName) {
    alert('Messaging coming soon.\nYou selected: '
          + userName);
}

// ── Zoom ──────────────────────────────────────
function zoomIn() {
    svg.transition().duration(250)
        .call(zoom.scaleBy, 1.4);
}

function zoomOut() {
    svg.transition().duration(250)
        .call(zoom.scaleBy, 0.7);
}

function resetView() {
    const el =
        document.getElementById('tree-page');
    const W = el.offsetWidth;
    const H = el.offsetHeight;
    svg.transition().duration(600)
        .call(zoom.transform,
            d3.zoomIdentity
                .translate(W/2, H/2)
                .scale(0.9)
        );
}

function toggleLegend() {
    const l = document.getElementById(
        'tree-legend'
    );
    l.style.display =
        l.style.display === 'block'
        ? 'none' : 'block';
}

function showEmpty() {
    document.getElementById('empty-state')
        .style.display = 'block';
    document.getElementById('member-count')
        .textContent = '0 members';
}

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

loadTree();
</script>

<?php require_once '../includes/footer.php'; ?>