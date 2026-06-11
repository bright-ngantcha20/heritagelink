<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$user     = currentUser();
$hasProfile = hasProfile($pdo, $user['id']);

if (!$hasProfile) {
    redirect(SITE_URL . '/settings/profile.php');
}

$myMember = getUserMember($pdo, $user['id']);
?>
<?php require_once '../includes/header.php'; ?>

<!-- Tree specific styles -->
<style>
#tree-container {
    width: 100%;
    height: calc(100vh - 70px);
    background: #0a0a18;
    position: relative;
    overflow: hidden;
}

#tree-svg {
    width: 100%;
    height: 100%;
    cursor: grab;
}

#tree-svg:active {
    cursor: grabbing;
}

/* Node circles */
.node-circle {
    stroke-width: 3;
    transition: all 0.2s;
    cursor: pointer;
}

.node-circle:hover {
    filter: brightness(1.3);
}

.node-circle.root {
    stroke: #00d4ff;
    fill: #1a1a3e;
}

.node-circle.male {
    stroke: #4a90d9;
    fill: #0d1a2e;
}

.node-circle.female {
    stroke: #d94a8a;
    fill: #2e0d1a;
}

.node-circle.deceased {
    stroke: #555;
    fill: #1a1a1a;
    stroke-dasharray: 5,3;
}

.node-circle.verified {
    filter: drop-shadow(0 0 6px rgba(0,212,255,0.4));
}

/* Node labels */
.node-name {
    fill: #e0e0e0;
    font-size: 12px;
    font-family: 'Segoe UI', sans-serif;
    font-weight: 500;
    text-anchor: middle;
    pointer-events: none;
}

.node-date {
    fill: #666;
    font-size: 10px;
    font-family: 'Segoe UI', sans-serif;
    text-anchor: middle;
    pointer-events: none;
}

.node-quarter {
    fill: #00d4ff;
    font-size: 9px;
    font-family: 'Segoe UI', sans-serif;
    text-anchor: middle;
    pointer-events: none;
    opacity: 0.7;
}

/* Links */
.link-parent {
    stroke: #4a90d9;
    stroke-width: 1.5;
    fill: none;
    opacity: 0.5;
}

.link-spouse {
    stroke: #d94a8a;
    stroke-width: 1.5;
    fill: none;
    stroke-dasharray: 4,2;
    opacity: 0.5;
}

.link-sibling {
    stroke: #4ad9a0;
    stroke-width: 1.5;
    fill: none;
    stroke-dasharray: 2,3;
    opacity: 0.4;
}

/* Toolbar */
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
    background: rgba(17,17,39,0.9);
    border: 1px solid #1e1e3a;
    color: #aaa;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    font-size: 0.82rem;
    cursor: pointer;
    backdrop-filter: blur(8px);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.toolbar-btn:hover {
    border-color: #00d4ff;
    color: #fff;
}

/* Member count badge */
#member-count {
    background: rgba(0,212,255,0.1);
    border: 1px solid rgba(0,212,255,0.2);
    color: #00d4ff;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    font-size: 0.82rem;
    backdrop-filter: blur(8px);
}

/* Zoom controls */
#zoom-controls {
    position: absolute;
    bottom: 2rem;
    right: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    z-index: 100;
}

.zoom-btn {
    width: 36px;
    height: 36px;
    background: rgba(17,17,39,0.9);
    border: 1px solid #1e1e3a;
    border-radius: 8px;
    color: #aaa;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.zoom-btn:hover {
    border-color: #00d4ff;
    color: #fff;
}

/* Legend */
#tree-legend {
    position: absolute;
    bottom: 2rem;
    left: 1rem;
    background: rgba(17,17,39,0.9);
    border: 1px solid #1e1e3a;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    backdrop-filter: blur(8px);
    z-index: 100;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
    font-size: 0.78rem;
    color: #888;
}

.legend-item:last-child {
    margin-bottom: 0;
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.legend-line {
    width: 20px;
    height: 2px;
    flex-shrink: 0;
}

/* Member detail panel */
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
    display: flex;
    flex-direction: column;
}

#detail-panel.open {
    right: 0;
}

#detail-panel-inner {
    padding: 1.5rem;
    flex: 1;
}

.detail-close {
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
    transition: all 0.2s;
}

.detail-close:hover {
    background: #2a2a4a;
    color: #fff;
}

/* Chat panel */
#chat-panel {
    position: absolute;
    bottom: 0;
    right: -380px;
    width: 360px;
    height: 480px;
    background: #111127;
    border: 1px solid #1e1e3a;
    border-radius: 16px 16px 0 0;
    z-index: 300;
    transition: right 0.3s ease;
    display: flex;
    flex-direction: column;
}

#chat-panel.open {
    right: 0;
}

/* Empty state */
#empty-state {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #555;
    display: none;
}
</style>

<div id="tree-container">

    <!-- Toolbar -->
    <div id="tree-toolbar">
        <div id="member-count">
            Loading tree...
        </div>
        <button class="toolbar-btn"
                onclick="resetView()">
            <i class="ti ti-home"></i> Reset
        </button>
        <button class="toolbar-btn"
                onclick="window.location.href=
                '<?= SITE_URL ?>/family/add.php'">
            <i class="ti ti-user-plus"></i>
            Add Member
        </button>
        <button class="toolbar-btn"
                id="toggle-legend-btn"
                onclick="toggleLegend()">
            <i class="ti ti-info-circle"></i>
            Legend
        </button>
    </div>

    <!-- SVG canvas -->
    <svg id="tree-svg">
        <defs>
            <marker id="arrow"
                    markerWidth="6"
                    markerHeight="6"
                    refX="5" refY="3"
                    orient="auto">
                <path d="M0,0 L0,6 L6,3 z"
                      fill="#4a90d9"
                      opacity="0.5"/>
            </marker>
        </defs>
        <g id="tree-g"></g>
    </svg>

    <!-- Empty state -->
    <div id="empty-state">
        <i class="ti ti-git-fork"
           style="font-size:3rem;
                  display:block;
                  margin-bottom:1rem;
                  color:#1e1e3a"></i>
        <h4 style="color:#666;margin-bottom:0.5rem">
            No connections yet
        </h4>
        <p style="font-size:0.9rem;margin-bottom:1rem">
            Add family members to build your tree
        </p>
        <a href="<?= SITE_URL ?>/family/add.php"
           style="
             background:#00d4ff;color:#000;
             padding:0.5rem 1.25rem;
             border-radius:8px;font-size:0.9rem;
             font-weight:600;text-decoration:none;
           ">
            Add First Member
        </a>
    </div>

    <!-- Zoom controls -->
    <div id="zoom-controls">
        <button class="zoom-btn"
                onclick="zoomIn()"
                title="Zoom in">+</button>
        <button class="zoom-btn"
                onclick="zoomOut()"
                title="Zoom out">−</button>
        <button class="zoom-btn"
                onclick="resetView()"
                title="Reset view"
                style="font-size:0.75rem">
            ↺
        </button>
    </div>

    <!-- Legend -->
    <div id="tree-legend">
        <div style="color:#aaa;font-size:0.75rem;
                    font-weight:600;
                    text-transform:uppercase;
                    letter-spacing:0.05em;
                    margin-bottom:0.6rem">
            Legend
        </div>
        <div class="legend-item">
            <div class="legend-dot"
                 style="background:#00d4ff;
                        border:2px solid #00d4ff">
            </div>
            You (root)
        </div>
        <div class="legend-item">
            <div class="legend-dot"
                 style="background:#0d1a2e;
                        border:2px solid #4a90d9">
            </div>
            Male member
        </div>
        <div class="legend-item">
            <div class="legend-dot"
                 style="background:#2e0d1a;
                        border:2px solid #d94a8a">
            </div>
            Female member
        </div>
        <div class="legend-item">
            <div class="legend-dot"
                 style="background:#1a1a1a;
                        border:2px dashed #555">
            </div>
            Deceased
        </div>
        <div style="
            border-top:1px solid #1e1e3a;
            margin:0.5rem 0;
        "></div>
        <div class="legend-item">
            <div class="legend-line"
                 style="background:#4a90d9">
            </div>
            Parent / Child
        </div>
        <div class="legend-item">
            <div class="legend-line"
                 style="background:#d94a8a;
                        background: repeating-linear-gradient(
                          90deg,#d94a8a 0,
                          #d94a8a 4px,
                          transparent 4px,
                          transparent 8px
                        )">
            </div>
            Spouse
        </div>
        <div class="legend-item">
            <div class="legend-line"
                 style="background: repeating-linear-gradient(
                          90deg,#4ad9a0 0,
                          #4ad9a0 2px,
                          transparent 2px,
                          transparent 5px
                        )">
            </div>
            Sibling
        </div>
    </div>

    <!-- Member detail panel -->
    <div id="detail-panel">
        <button class="detail-close"
                onclick="closePanel()">
            <i class="ti ti-x"></i>
        </button>
        <div id="detail-panel-inner">
            <!-- Filled by JavaScript -->
        </div>
    </div>

    <!-- Chat panel -->
    <div id="chat-panel">
        <div style="
            padding:1rem 1.25rem;
            border-bottom:1px solid #1e1e3a;
            display:flex;align-items:center;
            justify-content:space-between;
        ">
            <div id="chat-header"
                 style="color:#fff;
                        font-size:0.9rem;
                        font-weight:500">
                Messages
            </div>
            <button onclick="closeChat()"
                    style="background:#1e1e3a;
                           border:none;color:#888;
                           width:28px;height:28px;
                           border-radius:6px;
                           cursor:pointer;
                           display:flex;
                           align-items:center;
                           justify-content:center">
                <i class="ti ti-x"
                   style="font-size:0.85rem"></i>
            </button>
        </div>
        <div id="chat-messages"
             style="flex:1;overflow-y:auto;
                    padding:1rem;
                    display:flex;
                    flex-direction:column;
                    gap:0.5rem">
        </div>
        <div style="
            padding:0.75rem;
            border-top:1px solid #1e1e3a;
            display:flex;gap:0.5rem;
        ">
            <input type="text"
                   id="chat-input"
                   placeholder="Type a message..."
                   style="flex:1;background:#0d0d1a;
                          border:1px solid #1e1e3a;
                          color:#e0e0e0;
                          border-radius:8px;
                          padding:0.5rem 0.75rem;
                          font-size:0.85rem;
                          outline:none"
                   onkeypress="
                     if(event.key==='Enter')
                       sendMessage()">
            <button onclick="sendMessage()"
                    style="background:#00d4ff;
                           border:none;color:#000;
                           width:36px;height:36px;
                           border-radius:8px;
                           cursor:pointer;
                           display:flex;
                           align-items:center;
                           justify-content:center;
                           font-size:1rem;
                           flex-shrink:0">
                <i class="ti ti-send"></i>
            </button>
        </div>
    </div>

</div>

<!-- D3.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>

<script>
// ── Configuration ─────────────────────────────
const SITE_URL  = '<?= SITE_URL ?>';
const ROOT_ID   = <?= $myMember['member_id'] ?>;
const MY_NAME   = '<?= addslashes($user['name']) ?>';

// Node dimensions
const NODE_R    = 36;  // circle radius
const NODE_W    = 140; // card width
const NODE_H    = 80;  // card height

// ── State ─────────────────────────────────────
let treeData    = null;
let simulation  = null;
let svg         = null;
let g           = null;
let zoom        = null;
let selectedNode = null;
let chatReceiverId = null;

// ── Load tree data ────────────────────────────
async function loadTree() {
    try {
        const res = await fetch(
            SITE_URL + '/api/tree.php'
        );
        treeData = await res.json();

        if (treeData.error) {
            showEmptyState();
            return;
        }

        if (!treeData.nodes
            || treeData.nodes.length === 0) {
            showEmptyState();
            return;
        }

        // Update member count
        document.getElementById('member-count')
            .textContent =
            treeData.counts.total + ' member' +
            (treeData.counts.total !== 1 ? 's' : '');

        renderTree(treeData);

    } catch (err) {
        console.error('Tree load error:', err);
        showEmptyState();
    }
}

// ── Render tree with D3 ───────────────────────
function renderTree(data) {
    const container =
        document.getElementById('tree-container');
    const W = container.offsetWidth;
    const H = container.offsetHeight;

    svg = d3.select('#tree-svg');
    g   = d3.select('#tree-g');

    // ── Zoom behaviour ────────────────────────
    zoom = d3.zoom()
        .scaleExtent([0.2, 3])
        .on('zoom', (event) => {
            g.attr('transform', event.transform);
        });

    svg.call(zoom);

    // ── Force simulation ──────────────────────
    simulation = d3.forceSimulation(data.nodes)
        .force('link', d3.forceLink(data.links)
            .id(d => d.id)
            .distance(d => {
                if (d.type === 'spouse') return 100;
                if (d.type === 'sibling') return 120;
                return 160; // parent/child
            })
            .strength(0.8)
        )
        .force('charge', d3.forceManyBody()
            .strength(-400)
        )
        .force('center', d3.forceCenter(W/2, H/2))
        .force('collision', d3.forceCollide()
            .radius(NODE_R + 20)
        )
        .force('x', d3.forceX(W/2).strength(0.03))
        .force('y', d3.forceY()
            .y(d => {
                // Push parents up, children down
                const isParentOfRoot =
                    data.links.some(l =>
                        (l.source.id === d.id
                         || l.source === d.id)
                        && (l.target.id === ROOT_ID
                            || l.target === ROOT_ID)
                        && l.type === 'parent'
                    );
                const isChildOfRoot =
                    data.links.some(l =>
                        (l.source.id === ROOT_ID
                         || l.source === ROOT_ID)
                        && (l.target.id === d.id
                            || l.target === d.id)
                        && l.type === 'parent'
                    );
                if (d.id === ROOT_ID) return H/2;
                if (isParentOfRoot)   return H/2 - 200;
                if (isChildOfRoot)    return H/2 + 200;
                return H/2;
            })
            .strength(0.15)
        );

    // ── Draw links ────────────────────────────
    const link = g.selectAll('.tree-link')
        .data(data.links)
        .enter()
        .append('line')
        .attr('class', d =>
            'tree-link link-' + d.type
        );

    // ── Draw node groups ──────────────────────
    const node = g.selectAll('.tree-node')
        .data(data.nodes)
        .enter()
        .append('g')
        .attr('class', 'tree-node')
        .style('cursor', 'pointer')
        .call(d3.drag()
            .on('start', dragStart)
            .on('drag',  dragging)
            .on('end',   dragEnd)
        )
        .on('click', (event, d) => {
            event.stopPropagation();
            showDetail(d);
        });

    // ── Node card background ──────────────────
    node.append('rect')
        .attr('x',  -NODE_W/2)
        .attr('y',  -NODE_H/2)
        .attr('width',  NODE_W)
        .attr('height', NODE_H)
        .attr('rx', 12)
        .attr('ry', 12)
        .attr('fill', d => {
            if (d.id === ROOT_ID) return '#0d1a3e';
            if (d.is_deceased)    return '#111';
            if (d.gender === 'female') return '#1a0d1a';
            return '#0d1526';
        })
        .attr('stroke', d => {
            if (d.id === ROOT_ID) return '#00d4ff';
            if (d.is_deceased)    return '#444';
            if (d.gender === 'female') return '#d94a8a';
            return '#4a90d9';
        })
        .attr('stroke-width', d =>
            d.id === ROOT_ID ? 2.5 : 1.5
        )
        .attr('stroke-dasharray', d =>
            d.is_deceased ? '5,3' : 'none'
        );

    // ── Avatar circle ─────────────────────────
    node.append('circle')
        .attr('cx', -NODE_W/2 + 28)
        .attr('cy', 0)
        .attr('r',  18)
        .attr('fill', d => {
            if (d.id === ROOT_ID) return '#1a2a5e';
            if (d.is_deceased)    return '#222';
            if (d.gender === 'female') return '#2e1020';
            return '#0d1e3a';
        })
        .attr('stroke', d => {
            if (d.id === ROOT_ID) return '#00d4ff';
            if (d.is_deceased)    return '#555';
            if (d.gender === 'female') return '#d94a8a';
            return '#4a90d9';
        })
        .attr('stroke-width', 1.5);

    // ── Avatar initial ────────────────────────
    node.append('text')
        .attr('x', -NODE_W/2 + 28)
        .attr('y', 5)
        .attr('text-anchor', 'middle')
        .attr('font-size', '13px')
        .attr('font-weight', '600')
        .attr('font-family', 'Segoe UI, sans-serif')
        .attr('fill', d => {
            if (d.id === ROOT_ID) return '#00d4ff';
            if (d.is_deceased)    return '#555';
            if (d.gender === 'female') return '#d94a8a';
            return '#4a90d9';
        })
        .text(d =>
            d.name.charAt(0).toUpperCase()
        );

    // ── Name text ─────────────────────────────
    node.append('text')
        .attr('class', 'node-name')
        .attr('x', -NODE_W/2 + 54)
        .attr('y', d => d.date_range ? -8 : 5)
        .attr('text-anchor', 'start')
        .attr('font-size', '12px')
        .attr('font-weight', '500')
        .attr('font-family', 'Segoe UI, sans-serif')
        .attr('fill', d =>
            d.id === ROOT_ID ? '#fff' : '#ddd'
        )
        .text(d => {
            const name = d.preferred_name || d.name;
            // Truncate long names
            return name.length > 14
                ? name.substring(0, 13) + '…'
                : name;
        });

    // ── Date range ────────────────────────────
    node.filter(d => d.date_range)
        .append('text')
        .attr('x', -NODE_W/2 + 54)
        .attr('y', 8)
        .attr('text-anchor', 'start')
        .attr('font-size', '10px')
        .attr('font-family', 'Segoe UI, sans-serif')
        .attr('fill', '#666')
        .text(d => d.date_range);

    // ── Quarter badge ─────────────────────────
    node.append('text')
        .attr('x', -NODE_W/2 + 54)
        .attr('y', d => d.date_range ? 22 : 20)
        .attr('text-anchor', 'start')
        .attr('font-size', '9px')
        .attr('font-family', 'Segoe UI, sans-serif')
        .attr('fill', d =>
            d.quarter_id ? '#00d4ff' : '#888'
        )
        .attr('opacity', 0.8)
        .text(d => d.quarter
            ? d.quarter.substring(0, 16)
            : '');

    // ── Root YOU badge ────────────────────────
    node.filter(d => d.id === ROOT_ID)
        .append('rect')
        .attr('x',  NODE_W/2 - 36)
        .attr('y', -NODE_H/2 + 2)
        .attr('width',  32)
        .attr('height', 16)
        .attr('rx', 4)
        .attr('fill', '#00d4ff');

    node.filter(d => d.id === ROOT_ID)
        .append('text')
        .attr('x', NODE_W/2 - 20)
        .attr('y', -NODE_H/2 + 13)
        .attr('text-anchor', 'middle')
        .attr('font-size', '8px')
        .attr('font-weight', '700')
        .attr('font-family', 'Segoe UI, sans-serif')
        .attr('fill', '#000')
        .text('YOU');

    // ── Verified badge ────────────────────────
    node.filter(d => d.verified && d.id !== ROOT_ID)
        .append('circle')
        .attr('cx', NODE_W/2 - 10)
        .attr('cy', -NODE_H/2 + 10)
        .attr('r',  7)
        .attr('fill', '#00d4ff')
        .attr('opacity', 0.9);

    node.filter(d => d.verified && d.id !== ROOT_ID)
        .append('text')
        .attr('x', NODE_W/2 - 10)
        .attr('y', -NODE_H/2 + 14)
        .attr('text-anchor', 'middle')
        .attr('font-size', '8px')
        .attr('fill', '#000')
        .attr('font-weight', '700')
        .text('✓');

    // ── Deceased indicator ────────────────────
    node.filter(d => d.is_deceased)
        .append('text')
        .attr('x', NODE_W/2 - 10)
        .attr('y',  NODE_H/2 - 4)
        .attr('text-anchor', 'middle')
        .attr('font-size', '10px')
        .attr('fill', '#555')
        .text('†');

    // ── Click on SVG background to deselect ──
    svg.on('click', () => {
        closePanel();
    });

    // ── Simulation tick ───────────────────────
    simulation.on('tick', () => {
        link
            .attr('x1', d => d.source.x)
            .attr('y1', d => d.source.y)
            .attr('x2', d => d.target.x)
            .attr('y2', d => d.target.y);

        node.attr('transform',
            d => `translate(${d.x},${d.y})`
        );
    });

    // ── Auto center after stabilization ───────
    setTimeout(() => {
        resetView();
    }, 1500);
}

// ── Drag handlers ─────────────────────────────
function dragStart(event, d) {
    if (!event.active)
        simulation.alphaTarget(0.3).restart();
    d.fx = d.x;
    d.fy = d.y;
}

function dragging(event, d) {
    d.fx = event.x;
    d.fy = event.y;
}

function dragEnd(event, d) {
    if (!event.active)
        simulation.alphaTarget(0);
    d.fx = null;
    d.fy = null;
}

// ── Show member detail panel ──────────────────
function showDetail(d) {
    selectedNode = d;
    const panel =
        document.getElementById('detail-panel');
    const inner =
        document.getElementById(
            'detail-panel-inner'
        );

    const isRoot = d.id === ROOT_ID;
    const genderColor =
        d.gender === 'female' ? '#d94a8a' :
        d.id === ROOT_ID      ? '#00d4ff' :
        '#4a90d9';

    inner.innerHTML = `
        <div style="text-align:center;
                    padding-top:1rem;
                    margin-bottom:1.5rem">

            <!-- Avatar -->
            <div style="
                width:64px;height:64px;
                border-radius:50%;
                background:${d.gender === 'female'
                    ? '#2e0d1a' : '#0d1a2e'};
                border:2px solid ${genderColor};
                display:flex;align-items:center;
                justify-content:center;
                font-size:1.5rem;font-weight:600;
                color:${genderColor};
                margin:0 auto 1rem;
                ${d.is_deceased
                    ? 'border-style:dashed;'
                    : ''}
            ">
                ${d.name.charAt(0).toUpperCase()}
            </div>

            <!-- Name -->
            <h4 style="color:#fff;font-size:1.1rem;
                       font-weight:600;
                       margin-bottom:4px">
                ${escHtml(d.name)}
            </h4>

            ${d.preferred_name ? `
            <div style="color:#888;font-size:0.85rem;
                        margin-bottom:6px">
                "${escHtml(d.preferred_name)}"
            </div>` : ''}

            <!-- Quarter badge -->
            <div style="
                display:inline-block;
                background:rgba(0,212,255,0.1);
                border:1px solid rgba(0,212,255,0.2);
                color:#00d4ff;font-size:0.75rem;
                padding:3px 10px;border-radius:20px;
                margin-bottom:0.75rem;
            ">
                ${escHtml(d.quarter || 'Unknown')}
            </div>

            ${d.is_deceased ? `
            <div style="color:#555;font-size:0.8rem">
                † Deceased
            </div>` : ''}

            ${d.verified ? `
            <div style="color:#00d4ff;
                        font-size:0.78rem;
                        margin-top:4px">
                <i>✓ Verified record</i>
            </div>` : ''}
        </div>

        <!-- Vital stats -->
        <div style="
            background:#0d0d1a;
            border:1px solid #1e1e3a;
            border-radius:10px;
            padding:1rem;
            margin-bottom:1rem;
        ">
            ${d.date_range ? `
            <div style="
                display:flex;gap:8px;
                align-items:flex-start;
                margin-bottom:0.6rem;
            ">
                <span style="color:#555;
                             font-size:0.8rem;
                             min-width:20px">📅</span>
                <span style="color:#aaa;
                             font-size:0.85rem">
                    ${escHtml(d.date_range)}
                </span>
            </div>` : ''}

            ${d.birthplace ? `
            <div style="
                display:flex;gap:8px;
                align-items:flex-start;
                margin-bottom:0.6rem;
            ">
                <span style="color:#555;
                             font-size:0.8rem;
                             min-width:20px">📍</span>
                <span style="color:#aaa;
                             font-size:0.85rem">
                    ${escHtml(d.birthplace)}
                </span>
            </div>` : ''}

            ${d.occupation ? `
            <div style="
                display:flex;gap:8px;
                align-items:flex-start;
            ">
                <span style="color:#555;
                             font-size:0.8rem;
                             min-width:20px">💼</span>
                <span style="color:#aaa;
                             font-size:0.85rem">
                    ${escHtml(d.occupation)}
                </span>
            </div>` : ''}
        </div>

        <!-- Bio -->
        ${d.bio ? `
        <div style="
            color:#888;font-size:0.85rem;
            line-height:1.6;margin-bottom:1rem;
            padding:0.75rem;
            border-left:2px solid #1e1e3a;
        ">
            ${escHtml(d.bio)}
        </div>` : ''}

        <!-- Actions -->
        <div style="
            display:flex;flex-direction:column;
            gap:0.5rem;margin-top:1rem;
        ">
            ${!isRoot ? `
            <button onclick="openChat(${d.id},
                '${escHtml(d.name)}')"
                style="
                    background:#00d4ff;
                    border:none;color:#000;
                    padding:0.6rem;
                    border-radius:8px;
                    font-size:0.85rem;
                    font-weight:600;
                    cursor:pointer;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    gap:6px;
                ">
                💬 Send Message
            </button>` : ''}

            <a href="${SITE_URL}/family/add.php"
               style="
                    background:#1e1e3a;
                    border:1px solid #2a2a4a;
                    color:#aaa;
                    padding:0.6rem;
                    border-radius:8px;
                    font-size:0.85rem;
                    cursor:pointer;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    gap:6px;
                    text-decoration:none;
               ">
                + Add Their Relative
            </a>
        </div>
    `;

    panel.classList.add('open');
}

// ── Close detail panel ────────────────────────
function closePanel() {
    document.getElementById('detail-panel')
        .classList.remove('open');
    selectedNode = null;
}

// ── Chat panel ────────────────────────────────
function openChat(userId, userName) {
    chatReceiverId = userId;
    document.getElementById('chat-header')
        .textContent = '💬 ' + userName;
    document.getElementById('chat-messages')
        .innerHTML = `
        <div style="text-align:center;
                    color:#555;font-size:0.8rem;
                    padding:1rem">
            Start a conversation with
            ${escHtml(userName)}
        </div>`;
    document.getElementById('chat-panel')
        .classList.add('open');
    document.getElementById('chat-input')
        .focus();
    loadMessages(userId);
}

function closeChat() {
    document.getElementById('chat-panel')
        .classList.remove('open');
    chatReceiverId = null;
}

async function loadMessages(receiverId) {
    try {
        const res = await fetch(
            SITE_URL +
            '/api/messages.php?with=' + receiverId
        );
        const data = await res.json();
        if (data.messages) {
            renderMessages(data.messages);
        }
    } catch (e) {
        console.log('Messages not yet implemented');
    }
}

function renderMessages(messages) {
    const container =
        document.getElementById('chat-messages');
    if (!messages.length) return;

    container.innerHTML = messages.map(m => `
        <div style="
            display:flex;
            justify-content:${
                m.is_mine ? 'flex-end' : 'flex-start'
            };
        ">
            <div style="
                background:${
                    m.is_mine
                    ? '#00d4ff'
                    : '#1e1e3a'
                };
                color:${m.is_mine ? '#000' : '#ddd'};
                padding:0.5rem 0.75rem;
                border-radius:10px;
                font-size:0.82rem;
                max-width:75%;
                line-height:1.4;
            ">
                ${escHtml(m.message_text)}
                <div style="
                    font-size:0.7rem;
                    opacity:0.6;
                    margin-top:2px;
                    text-align:right;
                ">
                    ${m.time}
                </div>
            </div>
        </div>
    `).join('');

    container.scrollTop = container.scrollHeight;
}

async function sendMessage() {
    const input =
        document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text || !chatReceiverId) return;

    try {
        await fetch(
            SITE_URL + '/api/messages.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type':
                    'application/json'
                },
                body: JSON.stringify({
                    receiver_id: chatReceiverId,
                    message: text,
                })
            }
        );
        input.value = '';
        loadMessages(chatReceiverId);
    } catch (e) {
        console.log('Send failed');
    }
}

// ── Zoom controls ─────────────────────────────
function zoomIn() {
    svg.transition().duration(300)
        .call(zoom.scaleBy, 1.4);
}

function zoomOut() {
    svg.transition().duration(300)
        .call(zoom.scaleBy, 0.7);
}

function resetView() {
    const container =
        document.getElementById('tree-container');
    const W = container.offsetWidth;
    const H = container.offsetHeight;

    svg.transition().duration(600)
        .call(zoom.transform,
            d3.zoomIdentity
                .translate(W/2, H/2)
                .scale(0.85)
                .translate(-W/2, -H/2)
        );
}

// ── Legend toggle ─────────────────────────────
function toggleLegend() {
    const legend =
        document.getElementById('tree-legend');
    legend.style.display =
        legend.style.display === 'none'
        ? 'block' : 'none';
}

// ── Empty state ───────────────────────────────
function showEmptyState() {
    document.getElementById('empty-state')
        .style.display = 'block';
    document.getElementById('member-count')
        .textContent = '0 members';
}

// ── Utility: escape HTML ──────────────────────
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;');
}

// ── Init ──────────────────────────────────────
loadTree();
</script>

<?php require_once '../includes/footer.php'; ?>