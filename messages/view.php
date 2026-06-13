<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
$user    = currentUser();
$conv_id = (int)($_GET['id'] ?? 0);

if (!$conv_id) {
    header('Location: ' . SITE_URL . '/messages/inbox.php');
    exit;
}

// ── Verify user belongs to this conversation ─────
$conv = $pdo->prepare("
    SELECT c.*,
        CASE
            WHEN c.user_id_1 = ? THEN c.user_id_2
            ELSE c.user_id_1
        END AS other_id
    FROM conversations c
    WHERE c.conversation_id = ?
      AND (c.user_id_1 = ? OR c.user_id_2 = ?)
    LIMIT 1
");
$conv->execute([
    $user['id'], $conv_id,
    $user['id'], $user['id'],
]);
$conversation = $conv->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    header('Location: ' . SITE_URL . '/messages/inbox.php');
    exit;
}

$other_id = $conversation['other_id'];

// ── Fetch other user info ────────────────────────
$other_row = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.profile_photo,
           fm.occupation, q.name AS quarter_name
    FROM   users u
    LEFT JOIN family_members fm ON fm.member_id = u.member_id
    LEFT JOIN quarters q ON q.quarter_id = fm.quarter_id
    WHERE  u.user_id = ?
");
$other_row->execute([$other_id]);
$other = $other_row->fetch(PDO::FETCH_ASSOC);

if (!$other) {
    header('Location: ' . SITE_URL . '/messages/inbox.php');
    exit;
}

// ── Mark messages as read on open ───────────────
$pdo->prepare("
    UPDATE messages
    SET    is_read = 1
    WHERE  conversation_id = ?
      AND  receiver_id = ?
      AND  is_read = 0
")->execute([$conv_id, $user['id']]);

// ── Load initial messages (server-rendered) ──────
$msgs = $pdo->prepare("
    SELECT m.*,
           u.full_name     AS sender_name,
           u.profile_photo AS sender_photo
    FROM   messages m
    JOIN   users u ON u.user_id = m.sender_id
    WHERE  m.conversation_id = ?
    ORDER  BY m.sent_at ASC
");
$msgs->execute([$conv_id]);
$messages = $msgs->fetchAll(PDO::FETCH_ASSOC);

// Last message timestamp for polling
$last_ts = !empty($messages)
    ? end($messages)['sent_at']
    : '1970-01-01 00:00:00';

// Tree context
$tree_member_id = (int)($_GET['member'] ?? 0);
$tree_member    = null;
if ($tree_member_id) {
    $tm = $pdo->prepare(
        "SELECT full_name FROM family_members
         WHERE member_id = ?"
    );
    $tm->execute([$tree_member_id]);
    $tree_member = $tm->fetchColumn();
}

$other_photo = $other['profile_photo']
    ? SITE_URL . '/' . $other['profile_photo']
    : null;
$other_initial = strtoupper(
    substr($other['full_name'] ?? 'U', 0, 1)
);

function bubble_html($msg, $my_id,
    $other_photo, $other_initial,
    $site_url, $is_js = false)
{
    // Used server-side only; JS has its own renderer
    $is_mine   = (int)$msg['sender_id'] === (int)$my_id;
    $photo_url = $msg['sender_photo']
        ? $site_url . '/' . $msg['sender_photo']
        : null;
    $initial   = strtoupper(
        substr($msg['sender_name'] ?? 'U', 0, 1)
    );
    $br   = $is_mine
        ? '14px 14px 4px 14px'
        : '14px 14px 14px 4px';
    $bg   = $is_mine ? '#00d4ff' : '#1e1e3a';
    $col  = $is_mine ? '#000'    : '#e0e0e0';
    $just = $is_mine ? 'flex-end' : 'flex-start';
    $ts   = date('g:ia', strtotime($msg['sent_at']));
    $read_tick = $is_mine
        ? ($msg['is_read']
            ? '<span style="color:#00a8cc">✓✓</span>'
            : '<span style="color:#444">✓</span>')
        : '';
    $avatar = '';
    if (!$is_mine) {
        $img = $other_photo
            ? "<img src=\"{$other_photo}\"
                   style=\"width:100%;height:100%;
                           object-fit:cover\">"
            : $other_initial;
        $avatar = "
        <div style=\"width:28px;height:28px;
                     border-radius:50%;
                     background:rgba(0,212,255,0.1);
                     display:flex;align-items:center;
                     justify-content:center;
                     font-size:0.7rem;font-weight:700;
                     color:#00d4ff;flex-shrink:0;
                     overflow:hidden;\">
            {$img}
        </div>";
    }
    $text = nl2br(htmlspecialchars(
        $msg['message_text'],
        ENT_QUOTES, 'UTF-8'
    ));
    return "
    <div class=\"msg-row\"
         data-id=\"{$msg['message_id']}\"
         style=\"display:flex;
                 justify-content:{$just};
                 gap:0.5rem;
                 align-items:flex-end;
                 margin-bottom:0.15rem;\">
        {$avatar}
        <div style=\"max-width:72%\">
            <div style=\"background:{$bg};
                         color:{$col};
                         padding:0.6rem 0.9rem;
                         border-radius:{$br};
                         font-size:0.88rem;
                         line-height:1.5;
                         word-break:break-word;\">
                {$text}
            </div>
            <div style=\"font-size:0.7rem;color:#333;
                         margin-top:3px;
                         text-align:" . ($is_mine ? 'right' : 'left') . ";\">
                {$ts} {$read_tick}
            </div>
        </div>
    </div>";
}
?>
<?php require_once '../includes/header.php'; ?>

<style>
#thread::-webkit-scrollbar { width:4px; }
#thread::-webkit-scrollbar-track { background:transparent; }
#thread::-webkit-scrollbar-thumb {
    background:#2a2a4a;border-radius:4px; }
.date-divider {
    text-align:center;font-size:0.72rem;
    color:#333;margin:0.75rem 0;
    display:flex;align-items:center;gap:0.75rem;
}
.date-divider::before,
.date-divider::after {
    content:'';flex:1;height:1px;
    background:#1a1a30;
}
#msg_input:focus { border-color:#00d4ff !important; }
#send_btn:hover  { opacity:0.85; }
</style>

<main style="
  padding:0;
  height:calc(100vh - 62px);
  display:flex;flex-direction:column;
  overflow:hidden;
">

<!-- ── Top bar ──────────────────────────────── -->
<div style="
  display:flex;align-items:center;gap:1rem;
  padding:0.75rem 1.25rem;
  background:#111127;
  border-bottom:1px solid #1e1e3a;
  flex-shrink:0;
">
  <a href="<?= SITE_URL ?>/messages/inbox.php"
     style="color:#555;text-decoration:none;
            font-size:1.2rem;line-height:1">
    <i class="ti ti-arrow-left"></i>
  </a>

  <div style="
    width:38px;height:38px;border-radius:50%;
    background:rgba(0,212,255,0.1);
    border:1px solid rgba(0,212,255,0.2);
    display:flex;align-items:center;
    justify-content:center;
    font-size:0.95rem;font-weight:700;
    color:#00d4ff;overflow:hidden;flex-shrink:0;
  ">
    <?php if ($other_photo): ?>
      <img src="<?= $other_photo ?>"
           style="width:100%;height:100%;
                  object-fit:cover">
    <?php else: ?>
      <?= $other_initial ?>
    <?php endif; ?>
  </div>

  <div style="flex:1;min-width:0">
    <div style="
      color:#fff;font-weight:600;font-size:0.92rem;
      overflow:hidden;text-overflow:ellipsis;
      white-space:nowrap;
    ">
      <?= clean($other['full_name']) ?>
    </div>
    <?php if ($other['quarter_name']): ?>
    <div style="color:#555;font-size:0.75rem">
      <?= clean($other['quarter_name']) ?>
      <?= $other['occupation']
          ? ' · ' . clean($other['occupation']) : '' ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Typing indicator -->
  <div id="typing_indicator"
       style="display:none;color:#00d4ff;
              font-size:0.78rem">
    <span class="ti ti-dots"></span> typing…
  </div>
</div>

<!-- ── Tree context banner ─────────────────── -->
<?php if ($tree_member && empty($messages)): ?>
<div style="
  background:rgba(0,212,255,0.04);
  border-bottom:1px solid rgba(0,212,255,0.1);
  padding:0.6rem 1.25rem;
  font-size:0.82rem;color:#666;flex-shrink:0;
">
  <i class="ti ti-git-fork me-1"
     style="color:#00d4ff"></i>
  Messaging about
  <strong style="color:#aaa">
    <?= clean($tree_member) ?>
  </strong>
  from the family tree.
</div>
<?php endif; ?>

<!-- ── Message thread ───────────────────────── -->
<div id="thread" style="
  flex:1;overflow-y:auto;
  padding:1.1rem 1.25rem;
  display:flex;flex-direction:column;
">

<?php
if (empty($messages)):
?>
  <div id="empty_state" style="
    flex:1;display:flex;align-items:center;
    justify-content:center;flex-direction:column;
    color:#333;text-align:center;padding:2rem;
  ">
    <i class="ti ti-message"
       style="font-size:2.5rem;
              margin-bottom:0.75rem;
              color:#2a2a4a"></i>
    <div style="color:#555;font-size:0.9rem">
      Say hello to
      <strong style="color:#888">
        <?= clean($other['full_name']) ?>
      </strong>
    </div>
  </div>

<?php else:
  $prev_date = null;
  foreach ($messages as $msg):
    $msg_date = date('Y-m-d',
        strtotime($msg['sent_at']));
    if ($msg_date !== $prev_date):
      $today = date('Y-m-d');
      $yest  = date('Y-m-d', strtotime('-1 day'));
      $label = $msg_date === $today
          ? 'Today'
          : ($msg_date === $yest
              ? 'Yesterday'
              : date('d M Y',
                  strtotime($msg['sent_at'])));
      $prev_date = $msg_date;
?>
  <div class="date-divider"><?= $label ?></div>
<?php endif;
  echo bubble_html(
      $msg, $user['id'],
      $other_photo, $other_initial,
      SITE_URL
  );
endforeach;
endif;
?>

</div>

<!-- ── Reply box ─────────────────────────────── -->
<div style="
  padding:0.75rem 1.25rem 1rem;
  background:#111127;
  border-top:1px solid #1e1e3a;
  flex-shrink:0;
">
  <div id="send_error"
       style="display:none;color:#ff6b7a;
              font-size:0.8rem;
              margin-bottom:0.5rem"></div>

  <div style="display:flex;gap:0.65rem;
               align-items:flex-end">
    <textarea id="msg_input"
              placeholder="Write a message…"
              rows="1"
              style="
                flex:1;background:#0d0d1a;
                border:1px solid #1e1e3a;
                color:#e0e0e0;border-radius:12px;
                padding:0.65rem 0.9rem;
                font-size:0.88rem;outline:none;
                resize:none;overflow:hidden;
                max-height:120px;line-height:1.5;
                transition:border-color 0.15s;
              "></textarea>

    <button id="send_btn"
            style="
              width:42px;height:42px;
              background:#00d4ff;border:none;
              border-radius:50%;cursor:pointer;
              display:flex;align-items:center;
              justify-content:center;
              flex-shrink:0;
              transition:opacity 0.15s;
            ">
      <i class="ti ti-send"
         style="font-size:1rem;color:#000"></i>
    </button>
  </div>
</div>

</main>

<script>
// ── Constants ─────────────────────────────────
const CONV_ID   = <?= $conv_id ?>;
const MY_ID     = <?= $user['id'] ?>;
const SITE_URL  = '<?= SITE_URL ?>';
const OTHER_NAME  = <?= json_encode($other['full_name']) ?>;
const OTHER_PHOTO = <?= json_encode($other_photo) ?>;
const OTHER_INIT  = <?= json_encode($other_initial) ?>;
const CSRF_TOKEN  = <?= json_encode(csrfToken()) ?>;

// ── State ─────────────────────────────────────
let lastTs  = <?= json_encode($last_ts) ?>;
let sending = false;
let pollTimer;

// ── DOM refs ──────────────────────────────────
const thread   = document.getElementById('thread');
const input    = document.getElementById('msg_input');
const sendBtn  = document.getElementById('send_btn');
const errEl    = document.getElementById('send_error');
const emptyEl  = document.getElementById('empty_state');

// ── Scroll to bottom ──────────────────────────
function scrollBottom(smooth = false) {
    thread.scrollTo({
        top: thread.scrollHeight,
        behavior: smooth ? 'smooth' : 'auto',
    });
}
scrollBottom();

// ── Auto-resize textarea ──────────────────────
input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(
        input.scrollHeight, 120) + 'px';
});

// ── Time formatting ───────────────────────────
function fmtTime(ts) {
    const d = new Date(ts.replace(' ', 'T'));
    return d.toLocaleTimeString([], {
        hour: '2-digit', minute: '2-digit'
    });
}
function fmtDate(ts) {
    const d    = new Date(ts.replace(' ', 'T'));
    const now  = new Date();
    const diff = Math.floor(
        (now - d) / 86400000
    );
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    return d.toLocaleDateString([], {
        day:'numeric', month:'short', year:'numeric'
    });
}

// ── Track last rendered date ──────────────────
let lastRenderedDate = <?= json_encode(
    !empty($messages)
        ? date('Y-m-d', strtotime(end($messages)['sent_at']))
        : ''
) ?>;

function needDateDivider(ts) {
    const d = new Date(ts.replace(' ', 'T'));
    const dateStr = d.toISOString().slice(0, 10);
    if (dateStr !== lastRenderedDate) {
        lastRenderedDate = dateStr;
        return fmtDate(ts);
    }
    return null;
}

// ── Render a message bubble ───────────────────
function renderBubble(msg) {
    const isMine = msg.sender_id === MY_ID;
    const br     = isMine
        ? '14px 14px 4px 14px'
        : '14px 14px 14px 4px';
    const bg     = isMine ? '#00d4ff' : '#1e1e3a';
    const col    = isMine ? '#000'    : '#e0e0e0';
    const just   = isMine ? 'flex-end' : 'flex-start';
    const ts     = fmtTime(msg.sent_at);
    const tick   = isMine
        ? (msg.is_read
            ? '<span style="color:#00a8cc">✓✓</span>'
            : '<span style="color:#444">✓</span>')
        : '';
    const text   = msg.message_text
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/\n/g,'<br>');

    let avatar = '';
    if (!isMine) {
        const img = OTHER_PHOTO
            ? `<img src="${OTHER_PHOTO}"
                    style="width:100%;height:100%;
                           object-fit:cover">`
            : OTHER_INIT;
        avatar = `
        <div style="width:28px;height:28px;
                    border-radius:50%;
                    background:rgba(0,212,255,0.1);
                    display:flex;align-items:center;
                    justify-content:center;
                    font-size:0.7rem;font-weight:700;
                    color:#00d4ff;flex-shrink:0;
                    overflow:hidden;">
            ${img}
        </div>`;
    }

    const el = document.createElement('div');
    el.className = 'msg-row';
    el.dataset.id = msg.message_id;
    el.style.cssText = `
        display:flex;justify-content:${just};
        gap:0.5rem;align-items:flex-end;
        margin-bottom:0.15rem;`;
    el.innerHTML = `
        ${avatar}
        <div style="max-width:72%">
            <div style="background:${bg};color:${col};
                         padding:0.6rem 0.9rem;
                         border-radius:${br};
                         font-size:0.88rem;
                         line-height:1.5;
                         word-break:break-word;">
                ${text}
            </div>
            <div style="font-size:0.7rem;color:#333;
                         margin-top:3px;
                         text-align:${isMine
                             ? 'right' : 'left'};">
                ${ts} ${tick}
            </div>
        </div>`;
    return el;
}

// ── Append messages to thread ─────────────────
function appendMessages(msgs) {
    if (!msgs.length) return;

    // Remove empty state
    if (emptyEl) emptyEl.style.display = 'none';

    const wasAtBottom =
        thread.scrollHeight - thread.scrollTop
        <= thread.clientHeight + 60;

    msgs.forEach(msg => {
        // Date divider if needed
        const label = needDateDivider(msg.sent_at);
        if (label) {
            const div = document.createElement('div');
            div.className = 'date-divider';
            div.textContent = label;
            thread.appendChild(div);
        }
        thread.appendChild(renderBubble(msg));
        lastTs = msg.sent_at;
    });

    if (wasAtBottom) scrollBottom(true);
}

// ── Update read ticks for sent messages ───────
function updateReadTicks() {
    document.querySelectorAll('.msg-row').forEach(row => {
        const tick = row.querySelector(
            'span[style*="color:#444"]'
        );
        if (tick) {
            tick.style.color = '#00a8cc';
            tick.textContent = '✓✓';
        }
    });
}

// ── Send a message ────────────────────────────
async function sendMessage() {
    const text = input.value.trim();
    if (!text || sending) return;

    sending = true;
    sendBtn.style.opacity = '0.5';
    errEl.style.display   = 'none';

    // Optimistic render
    const now = new Date();
    const optimistic = {
        message_id:   'temp_' + Date.now(),
        sender_id:    MY_ID,
        receiver_id:  0,
        message_text: text,
        is_read:      false,
        sent_at:      now.toISOString()
            .replace('T', ' ')
            .slice(0, 19),
        sender_name:  '',
        sender_photo: null,
    };
    appendMessages([optimistic]);
    input.value = '';
    input.style.height = 'auto';

    try {
        const fd = new FormData();
        fd.append('action',       'send');
        fd.append('conv_id',      CONV_ID);
        fd.append('message_text', text);
        fd.append('csrf_token',   CSRF_TOKEN);

        const res  = await fetch(
            `${SITE_URL}/api/messages.php`,
            { method: 'POST', body: fd }
        );
        const data = await res.json();

        if (data.ok) {
            // Replace optimistic bubble with real one
            const tmpEl = document.querySelector(
                `[data-id="temp_${optimistic.message_id
                    .replace('temp_','')}"]`
            ) || document.querySelector(
                '[data-id^="temp_"]'
            );
            if (tmpEl) {
                const realEl = renderBubble(
                    data.message
                );
                tmpEl.replaceWith(realEl);
            }
            lastTs = data.message.sent_at;
        } else {
            errEl.textContent = data.error
                || 'Failed to send. Try again.';
            errEl.style.display = 'block';
            // Remove optimistic bubble
            const tmpEl = document.querySelector(
                '[data-id^="temp_"]'
            );
            if (tmpEl) tmpEl.remove();
            input.value = text; // restore
        }
    } catch (e) {
        errEl.textContent =
            'Network error. Check connection.';
        errEl.style.display = 'block';
        const tmpEl = document.querySelector(
            '[data-id^="temp_"]'
        );
        if (tmpEl) tmpEl.remove();
        input.value = text;
    }

    sending = false;
    sendBtn.style.opacity = '1';
    input.focus();
}

// ── Poll for new messages ─────────────────────
async function poll() {
    try {
        const res  = await fetch(
            `${SITE_URL}/api/messages.php`
            + `?action=fetch`
            + `&conv_id=${CONV_ID}`
            + `&since=${encodeURIComponent(lastTs)}`
        );
        const data = await res.json();

        if (data.ok && data.messages.length) {
            // Filter out messages I sent
            // (already rendered optimistically)
            const incoming = data.messages.filter(
                m => m.sender_id !== MY_ID
            );
            if (incoming.length) {
                appendMessages(incoming);
                // Mark as read
                fetch(`${SITE_URL}/api/messages.php`
                    + `?action=mark_read`
                    + `&conv_id=${CONV_ID}`
                );
            }
            // Update read receipts if other
            // person has read my messages
            const theyRead = data.messages.some(
                m => m.sender_id === MY_ID
                     && m.is_read
            );
            if (theyRead) updateReadTicks();
        }
    } catch (e) {
        // Silently fail — will retry next interval
    }
    pollTimer = setTimeout(poll, 3000);
}

// ── Update navbar unread badge ────────────────
async function updateBadge() {
    try {
        const res  = await fetch(
            `${SITE_URL}/api/messages.php`
            + `?action=unread_count`
        );
        const data = await res.json();
        const badge = document.getElementById(
            'msg_badge'
        );
        if (badge) {
            badge.textContent  = data.count || '';
            badge.style.display =
                data.count ? 'flex' : 'none';
        }
    } catch (e) {}
}

// ── Event listeners ───────────────────────────
sendBtn.addEventListener('click', sendMessage);

input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// ── Stop polling when tab hidden ──────────────
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearTimeout(pollTimer);
    } else {
        poll();
        updateBadge();
    }
});

// ── Start ─────────────────────────────────────
input.focus();
scrollBottom();
poll();
updateBadge();
</script>

<?php require_once '../includes/footer.php'; ?>