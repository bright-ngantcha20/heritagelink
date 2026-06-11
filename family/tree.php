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
    background: rgba(17,17,39,0.95);
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
    user-select: none;
}

#tree-svg:active { cursor: grabbing; }

.link-parent {
    stroke: #4a90d9;
    stroke-width: 2;
    fill: none;
    opacity: 0.7;
}

.link-spouse {
    stroke: #d94a8a;
    stroke-width: 1.5;
    fill: none;
    stroke-dasharray: 6,3;
    opacity: 0.6;
}

.link-sibling {
    stroke: #4ad9a0;
    stroke-width: 1.5;
    fill: none;
    stroke-dasharray: 3,4;
    opacity: 0.5;
}

.node-card {
    cursor: pointer;
}

.node-card .card-bg {
    transition: filter 0.15s;
}

.node-card:hover .card-bg {
    filter: brightness(1.3);
}

.node-card.selected .card-bg {
    filter: brightness(1.5);
}

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
    background: rgba(17,17,39,0.95);
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

#tree-legend {
    position: absolute;
    bottom: 1.5rem;
    left: 1rem;
    background: rgba(17,17,39,0.95);
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

#detail-panel {
    position: absolute;
    top: 0;
    right: -380px;
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
    top: 1rem;
    right: 1rem;
    background: #1e1e3a;
    border: none;
    color: #888;
    width: 32px;
    height: 32px;
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

#empty-state {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #555;
    display: none;
}

/* Pan hint */
#pan-hint {
    position: absolute;
    bottom: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(17,17,39,0.8);
    border: 1px solid #1e1e3a;
    color: #555;
    font-size: 0.75rem;
    padding: 0.35rem 0.85rem;
    border-radius: 20px;
    pointer-events: none;
    z-index: 50;
    transition: opacity 1s;
}
</style>

<div id="tree-page">

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

    <svg id="tree-svg">
        <g id="tree-g"></g>
    </svg>

    <div id="zoom-controls">
        <button class="zoom-btn"
                onclick="zoomIn()">+</button>
        <button class="zoom-btn"
                onclick="zoomOut()">−</button>
        <button class="zoom-btn"
                onclick="resetView()"
                title="Reset view"
                style="font-size:0.85rem">↺</button>
    </div>

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
            </div>You (root)
        </div>
        <div class="legend-row">
            <div style="width:14px;height:14px;
                        border-radius:3px;
                        background:#0d1526;
                        border:2px solid #4a90d9">
            </div>Male
        </div>
        <div class="legend-row">
            <div style="width:14px;height:14px;
                        border-radius:3px;
                        background:#1a0d1a;
                        border:2px solid #d94a8a">
            </div>Female
        </div>
        <div class="legend-row">
            <div style="width:14px;height:14px;
                        border-radius:3px;
                        background:#111;
                        border:2px dashed #555">
            </div>Deceased
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
                          90deg,#d94a8a 0,#d94a8a 5px,
                          transparent 5px,transparent 9px)">
            </div>Spouse
        </div>
        <div class="legend-row">
            <div style="width:22px;height:2px;
                        background:repeating-linear-gradient(
                          90deg,#4ad9a0 0,#4ad9a0 3px,
                          transparent 3px,transparent 7px)">
            </div>Sibling
        </div>
    </div>

    <div id="detail-panel">
        <button id="panel-close"
                onclick="closePanel()">
            <i class="ti ti-x"></i>
        </button>
        <div id="panel-content"></div>
    </div>

    <div id="pan-hint">
        🖱 Drag to pan · Scroll to zoom
    </div>

    <div id="empty-state">
        <i class="ti ti-git-fork"
           style="font-size:3rem;display:block;
                  margin-bottom:1rem;color:#1e1e3a">
        </i>
        <h4 style="color:#555;margin-bottom:0.5rem">
            No family members yet
        </h4>
        <a href="<?= SITE_URL ?>/family/add.php"
           style="background:#00d4ff;color:#000;
                  padding:0.5rem 1.25rem;
                  border-radius:8px;
                  font-weight:600;
                  text-decoration:none;
                  font-size:0.9rem;
                  margin-top:0.5rem;
                  display:inline-block">
            Add First Member
        </a>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>

<script>
const SITE_URL = '<?= SITE_URL ?>';
const ROOT_ID  = <?= $myMember['member_id'] ?>;

// Card dimensions
const CW = 155;  // card width
const CH = 72;   // card height
const GH = 160;  // vertical gap between generations
const GW = 185;  // horizontal gap between nodes

let svg, g, zoom;
let allNodes = [];
let allLinks = [];
let visibleNodeIds = new Set();
let positions = {};
let levelMap  = {};
let selectedId = null;

// ── Load and render ───────────────────────────
async function loadTree() {
    try {
        const res = await fetch(
            SITE_URL + '/api/tree.php'
        );
        const data = await res.json();

        if (data.error
            || !data.nodes
            || data.nodes.length === 0) {
            showEmpty();
            return;
        }

        allNodes = data.nodes;
        allLinks = data.links;

        document.getElementById('member-count')
            .textContent =
            data.counts.total + ' member' +
            (data.counts.total !== 1 ? 's' : '');

        // Start with root + direct connections
        visibleNodeIds = new Set([ROOT_ID]);
        allLinks.forEach(l => {
            const src = l.source.id ?? l.source;
            const tgt = l.target.id ?? l.target;
            if (src === ROOT_ID)
                visibleNodeIds.add(tgt);
            if (tgt === ROOT_ID)
                visibleNodeIds.add(src);
        });

        initSVG();
        buildAndRender();

        // Hide pan hint after 4 seconds
        setTimeout(() => {
            const hint =
                document.getElementById('pan-hint');
            if (hint) hint.style.opacity = '0';
        }, 4000);

    } catch(e) {
        console.error(e);
        showEmpty();
    }
}

// ── Init SVG and zoom/pan ─────────────────────
function initSVG() {
    svg  = d3.select('#tree-svg');
    g    = d3.select('#tree-g');

    zoom = d3.zoom()
        .scaleExtent([0.1, 3])
        .on('zoom', ev => {
            g.attr('transform', ev.transform);
        });

    // Enable pan and zoom on the SVG
    svg.call(zoom)
        .on('dblclick.zoom', null); // disable dbl-click zoom

    // Click background to close panel
    svg.on('click', () => {
        closePanel();
        clearSelected();
    });
}

// ── Build levels and render ───────────────────
function buildAndRender() {
    const nodes = allNodes.filter(
        n => visibleNodeIds.has(n.id)
    );
    const links = allLinks.filter(l => {
        const src = l.source.id ?? l.source;
        const tgt = l.target.id ?? l.target;
        return visibleNodeIds.has(src)
            && visibleNodeIds.has(tgt);
    });

    // Assign generation levels
    levelMap = {};
    levelMap[ROOT_ID] = 0;

    const visited = new Set([ROOT_ID]);
    const queue   = [ROOT_ID];

    while (queue.length) {
        const current      = queue.shift();
        const currentLevel = levelMap[current];

        links.forEach(l => {
            const src = l.source.id ?? l.source;
            const tgt = l.target.id ?? l.target;
            const type = l.type;
            const lbl  = l.label || '';

            // current → tgt as parent
            // tgt is ABOVE current
            if (src === current
                && type === 'parent'
                && !visited.has(tgt)) {

                let offset = -1;
                if (lbl.includes('grandfather')
                 || lbl.includes('grandmother'))
                    offset = -2;
                else if (lbl.includes('great_'))
                    offset = -3;

                levelMap[tgt] = currentLevel + offset;
                visited.add(tgt);
                queue.push(tgt);
            }

            // current → tgt as child
            // tgt is BELOW current
            if (src === current
                && type === 'child'
                && !visited.has(tgt)) {
                levelMap[tgt] = currentLevel + 1;
                visited.add(tgt);
                queue.push(tgt);
            }

            // tgt === current with type child
            // src is BELOW current
            if (tgt === current
                && type === 'child'
                && !visited.has(src)) {
                levelMap[src] = currentLevel + 1;
                visited.add(src);
                queue.push(src);
            }

            // tgt === current with type parent
            // src is ABOVE current
            if (tgt === current
                && type === 'parent'
                && !visited.has(src)) {

                // Find the label from root to src
                const rootToSrc = allLinks.find(x =>
                    (x.source.id ?? x.source)
                        === ROOT_ID
                    && (x.target.id ?? x.target)
                        === src
                );
                const srcLbl =
                    rootToSrc?.label || '';
                let offset = -1;
                if (srcLbl.includes('grandfather')
                 || srcLbl.includes('grandmother'))
                    offset = -2;
                else if (srcLbl.includes('great_'))
                    offset = -3;

                levelMap[src] = currentLevel + offset;
                visited.add(src);
                queue.push(src);
            }

            // Spouse / sibling → same level
            if ((type === 'spouse'
                 || type === 'sibling')) {
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

    // Fallback for unvisited
    nodes.forEach(n => {
        if (levelMap[n.id] === undefined)
            levelMap[n.id] = 0;
    });

    // Group by level
    const levels = {};
    nodes.forEach(n => {
        const lv = levelMap[n.id] ?? 0;
        if (!levels[lv]) levels[lv] = [];
        levels[lv].push(n);
    });

    const sortedLevels = Object.keys(levels)
        .map(Number)
        .sort((a, b) => a - b);

    // Assign positions
    positions = {};
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

    renderAll(nodes, links, sortedLevels, levels);
}

// ── Render nodes and links ────────────────────
function renderAll(
    nodes, links, sortedLevels, levels
) {
    // Clear previous render
    g.selectAll('*').remove();

    const genLabels = {
        '-4': 'Great Great Grandparents',
        '-3': 'Great Grandparents',
        '-2': 'Grandparents',
        '-1': 'Parents',
         '0': 'Your Generation',
         '1': 'Children',
         '2': 'Grandchildren',
         '3': 'Great Grandchildren',
    };

    // ── Generation row labels ─────────────────
    sortedLevels.forEach(lv => {
        const label = genLabels[lv];
        if (!label) return;
        const y = lv * GH;

        g.append('line')
            .attr('x1', -3000)
            .attr('y1', y - CH/2 - 20)
            .attr('x2',  3000)
            .attr('y2', y - CH/2 - 20)
            .attr('stroke', '#131325')
            .attr('stroke-width', 1);

        g.append('text')
            .attr('x', -700)
            .attr('y', y - CH/2 - 6)
            .attr('font-size', '10px')
            .attr('font-family',
                  'Segoe UI, sans-serif')
            .attr('fill', '#252545')
            .attr('font-weight', '700')
            .attr('letter-spacing', '0.1em')
            .text(label.toUpperCase());
    });

    // ── Draw links ────────────────────────────
    links.forEach(l => {
        const src = l.source.id ?? l.source;
        const tgt = l.target.id ?? l.target;
        const p1  = positions[src];
        const p2  = positions[tgt];
        if (!p1 || !p2) return;

        const lvSrc = levelMap[src] ?? 0;
        const lvTgt = levelMap[tgt] ?? 0;

        if (l.type === 'parent') {
            // Only draw if one is directly
            // above the other
            let ancestor, descendant,
                pAnc, pDesc;

            if (lvSrc < lvTgt) {
                ancestor   = src;
                descendant = tgt;
                pAnc  = p1;
                pDesc = p2;
            } else if (lvTgt < lvSrc) {
                ancestor   = tgt;
                descendant = src;
                pAnc  = p2;
                pDesc = p1;
            } else {
                return; // same level, skip
            }

            // Draw curved path
            // from bottom of ancestor
            // to top of descendant
            const x1 = pAnc.x;
            const y1 = pAnc.y + CH/2;
            const x2 = pDesc.x;
            const y2 = pDesc.y - CH/2;
            const midY = (y1 + y2) / 2;

            g.append('path')
                .attr('class', 'link-parent')
                .attr('d', `
                    M ${x1} ${y1}
                    C ${x1} ${midY},
                      ${x2} ${midY},
                      ${x2} ${y2}
                `);

        } else if (l.type === 'spouse') {
            // Horizontal line between spouses
            // only draw once (src < tgt)
            if (src < tgt) {
                const leftX  =
                    Math.min(p1.x, p2.x) + CW/2;
                const rightX =
                    Math.max(p1.x, p2.x) - CW/2;
                const midY =
                    (p1.y + p2.y) / 2;

                g.append('line')
                    .attr('class', 'link-spouse')
                    .attr('x1', leftX)
                    .attr('y1', midY)
                    .attr('x2', rightX)
                    .attr('y2', midY);
            }

        } else if (l.type === 'sibling') {
            // Arc between siblings
            // only draw once
            if (src < tgt) {
                const leftX  =
                    Math.min(p1.x, p2.x) + CW/2;
                const rightX =
                    Math.max(p1.x, p2.x) - CW/2;
                const midY = p1.y;

                g.append('line')
                    .attr('class', 'link-sibling')
                    .attr('x1', leftX)
                    .attr('y1', midY)
                    .attr('x2', rightX)
                    .attr('y2', midY);
            }
        }
    });

    // ── Draw nodes ────────────────────────────
    nodes.forEach(n => {
        const pos    = positions[n.id];
        if (!pos) return;

        const isRoot = n.id === ROOT_ID;
        const isSelected = n.id === selectedId;

        const accentColor =
            isRoot         ? '#00d4ff' :
            n.is_deceased  ? '#555'    :
            n.gender === 'female' ? '#d94a8a' :
            '#4a90d9';

        const fillColor =
            isRoot         ? '#0d1a3e' :
            n.is_deceased  ? '#0f0f0f' :
            n.gender === 'female' ? '#1a0d1a' :
            '#0d1526';

        const ng = g.append('g')
            .attr('class',
                'node-card'
                + (isSelected ? ' selected' : ''))
            .attr('data-id', n.id)
            .attr('transform',
                `translate(${pos.x},${pos.y})`)
            .style('cursor', 'pointer')
            .on('click', (event) => {
                event.stopPropagation();
                onNodeClick(n);
            });

        // Card background
        ng.append('rect')
            .attr('class', 'card-bg')
            .attr('x',  -CW/2)
            .attr('y',  -CH/2)
            .attr('width',  CW)
            .attr('height', CH)
            .attr('rx', 10)
            .attr('ry', 10)
            .attr('fill', isSelected
                ? d3.color(fillColor).brighter(0.5)
                : fillColor)
            .attr('stroke', isSelected
                ? '#fff'
                : accentColor)
            .attr('stroke-width',
                isRoot ? 2.5 :
                isSelected ? 2.5 : 1.5)
            .attr('stroke-dasharray',
                n.is_deceased ? '5,3' : 'none');

        // Avatar circle
        ng.append('circle')
            .attr('cx', -CW/2 + 24)
            .attr('cy', 0)
            .attr('r',  16)
            .attr('fill', isRoot
                ? '#1a2a5e'
                : n.is_deceased
                    ? '#1a1a1a'
                    : n.gender === 'female'
                        ? '#2e0d2e'
                        : '#0d1e3a')
            .attr('stroke', accentColor)
            .attr('stroke-width', 1.5);

        // Initial letter
        ng.append('text')
            .attr('x', -CW/2 + 24)
            .attr('y', 5)
            .attr('text-anchor', 'middle')
            .attr('font-size', '12px')
            .attr('font-weight', '700')
            .attr('font-family',
                  'Segoe UI, sans-serif')
            .attr('fill', accentColor)
            .text(n.name.charAt(0).toUpperCase());

        // Name
        const dName = n.preferred_name || n.name;
        const tName = dName.length > 11
            ? dName.substring(0, 10) + '…'
            : dName;

        ng.append('text')
            .attr('x', -CW/2 + 47)
            .attr('y', n.date_range ? -10 : 2)
            .attr('font-size', '12px')
            .attr('font-weight', '600')
            .attr('font-family',
                  'Segoe UI, sans-serif')
            .attr('fill', isRoot ? '#fff' : '#ddd')
            .text(tName);

        // Date range
        if (n.date_range) {
            ng.append('text')
                .attr('x', -CW/2 + 47)
                .attr('y', 4)
                .attr('font-size', '9px')
                .attr('font-family',
                      'Segoe UI, sans-serif')
                .attr('fill', '#666')
                .text(n.date_range);
        }

        // Quarter
        if (n.quarter) {
            ng.append('text')
                .attr('x', -CW/2 + 47)
                .attr('y', n.date_range ? 18 : 17)
                .attr('font-size', '8.5px')
                .attr('font-family',
                      'Segoe UI, sans-serif')
                .attr('fill',
                    n.quarter_id
                    ? '#00d4ff' : '#777')
                .attr('opacity', 0.85)
                .text(n.quarter.length > 13
                    ? n.quarter.substring(0,12) + '…'
                    : n.quarter);
        }

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
                .attr('cx', CW/2 - 9)
                .attr('cy', -CH/2 + 9)
                .attr('r',  7)
                .attr('fill', '#00d4ff');

            ng.append('text')
                .attr('x', CW/2 - 9)
                .attr('y', -CH/2 + 13)
                .attr('text-anchor', 'middle')
                .attr('font-size', '8px')
                .attr('font-weight', '700')
                .attr('fill', '#000')
                .text('✓');
        }

        // Deceased cross
        if (n.is_deceased) {
            ng.append('text')
                .attr('x',  CW/2 - 9)
                .attr('y',  CH/2 - 4)
                .attr('text-anchor', 'middle')
                .attr('font-size', '11px')
                .attr('fill', '#555')
                .text('†');
        }

        // Expand indicator
        // Show + if this node has connections
        // not yet visible
        const hasHidden = allLinks.some(l => {
            const src = l.source.id ?? l.source;
            const tgt = l.target.id ?? l.target;
            return (src === n.id
                    && !visibleNodeIds.has(tgt))
                || (tgt === n.id
                    && !visibleNodeIds.has(src));
        });

        if (hasHidden) {
            ng.append('circle')
                .attr('cx', 0)
                .attr('cy', CH/2)
                .attr('r',  8)
                .attr('fill', '#1e1e3a')
                .attr('stroke', '#00d4ff')
                .attr('stroke-width', 1.5);

            ng.append('text')
                .attr('x', 0)
                .attr('y', CH/2 + 4)
                .attr('text-anchor', 'middle')
                .attr('font-size', '11px')
                .attr('font-weight', '700')
                .attr('font-family',
                      'Segoe UI, sans-serif')
                .attr('fill', '#00d4ff')
                .text('+');
        }
    });

    // Animate in
    g.selectAll('.node-card')
        .style('opacity', 0)
        .transition()
        .duration(400)
        .style('opacity', 1);
}

// ── Node click handler ────────────────────────
function onNodeClick(n) {
    selectedId = n.id;

    // Expand: add all connections
    // of this node to visible set
    let added = false;
    allLinks.forEach(l => {
        const src = l.source.id ?? l.source;
        const tgt = l.target.id ?? l.target;
        if (src === n.id
            && !visibleNodeIds.has(tgt)) {
            visibleNodeIds.add(tgt);
            added = true;
        }
        if (tgt === n.id
            && !visibleNodeIds.has(src)) {
            visibleNodeIds.add(src);
            added = true;
        }
    });

    // Re-render with expanded nodes
    buildAndRender();

    // Show detail panel
    showDetail(n);
}

// ── Detail panel ──────────────────────────────
function showDetail(n) {
    const isRoot = n.id === ROOT_ID;
    const gc =
        isRoot ? '#00d4ff' :
        n.gender === 'female' ? '#d94a8a' :
        '#4a90d9';

    // Find this node's connections
    // from allLinks
    const connections = [];
    allLinks.forEach(l => {
        const src = l.source.id ?? l.source;
        const tgt = l.target.id ?? l.target;
        let otherId = null;

        if (src === n.id) otherId = tgt;
        else if (tgt === n.id) otherId = src;

        if (otherId !== null) {
            const other = allNodes.find(
                x => x.id === otherId
            );
            if (other) {
                connections.push({
                    member: other,
                    type:   l.type,
                    label:  l.label,
                    isSrc:  src === n.id,
                });
            }
        }
    });

    // Remove duplicate connections
    const seen = new Set();
    const uniqueConn = connections.filter(c => {
        const key = c.member.id + '_' + c.type;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });

    document.getElementById('panel-content')
        .innerHTML = `

        <div style="text-align:center;
                    margin-bottom:1.25rem">
            <div style="
                width:58px;height:58px;
                border-radius:50%;
                background:${
                    n.gender === 'female'
                    ? '#2e0d2e' : '#0d1e3a'};
                border:2px solid ${gc};
                ${n.is_deceased
                    ? 'border-style:dashed;' : ''}
                display:flex;align-items:center;
                justify-content:center;
                font-size:1.3rem;font-weight:700;
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
                        font-size:0.8rem;
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
                ✓ Verified
            </div>` : ''}
        </div>

        <!-- Vital stats -->
        ${(n.date_range || n.birthplace
           || n.occupation) ? `
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
                             font-size:0.8rem">📅
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
                             font-size:0.8rem">📍
                </span>
                <span style="color:#aaa;
                             font-size:0.82rem">
                    ${esc(n.birthplace)}
                </span>
            </div>` : ''}
            ${n.occupation ? `
            <div style="display:flex;gap:8px">
                <span style="color:#555;
                             font-size:0.8rem">💼
                </span>
                <span style="color:#aaa;
                             font-size:0.82rem">
                    ${esc(n.occupation)}
                </span>
            </div>` : ''}
        </div>` : ''}

        <!-- Bio -->
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

        <!-- Connections -->
        ${uniqueConn.length ? `
        <div style="margin-bottom:1rem">
            <div style="
                font-size:0.72rem;font-weight:600;
                text-transform:uppercase;
                letter-spacing:0.06em;
                color:#555;margin-bottom:0.6rem;
            ">
                Connections
            </div>
            ${uniqueConn.map(c => `
            <div style="
                display:flex;align-items:center;
                gap:0.6rem;padding:0.5rem 0;
                border-bottom:1px solid #1a1a2e;
                cursor:pointer;
            "
            onclick="onNodeClick(
                allNodes.find(x => x.id === ${c.member.id})
            )">
                <div style="
                    width:30px;height:30px;
                    border-radius:50%;
                    background:#1e1e3a;
                    display:flex;align-items:center;
                    justify-content:center;
                    font-size:0.85rem;font-weight:700;
                    color:#00d4ff;flex-shrink:0;
                ">
                    ${c.member.name.charAt(0)
                        .toUpperCase()}
                </div>
                <div style="flex:1;min-width:0">
                    <div style="color:#ddd;
                                font-size:0.85rem;
                                white-space:nowrap;
                                overflow:hidden;
                                text-overflow:ellipsis">
                        ${esc(c.member.name)}
                    </div>
                    <div style="color:#00d4ff;
                                font-size:0.75rem">
                        ${esc(c.type)}
                    </div>
                </div>
                <div style="color:#333;
                            font-size:0.8rem">›</div>
            </div>`).join('')}
        </div>` : ''}

        <!-- Actions -->
        <div style="
            display:flex;flex-direction:column;
            gap:0.5rem;margin-top:0.5rem;
        ">
            ${!isRoot ? `
            <button onclick="alert(
                'Messaging coming soon')"
                style="
                    background:#00d4ff;
                    border:none;color:#000;
                    padding:0.65rem;
                    border-radius:8px;
                    font-size:0.85rem;
                    font-weight:600;
                    cursor:pointer;width:100%;
                ">
                💬 Send Message
            </button>` : `
            <div style="
                background:rgba(0,212,255,0.06);
                border:1px solid rgba(0,212,255,0.15);
                border-radius:8px;padding:0.65rem;
                text-align:center;
                color:#00d4ff;font-size:0.82rem;
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

function clearSelected() {
    selectedId = null;
    buildAndRender();
}

// ── Zoom controls ─────────────────────────────
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
    const l =
        document.getElementById('tree-legend');
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
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;');
}

loadTree();
</script>

<?php require_once '../includes/footer.php'; ?>