<?php

// ── Output ────────────────────────────────────
function clean($str) {
    return htmlspecialchars(
        trim($str), ENT_QUOTES, 'UTF-8'
    );
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ── Date formatting ───────────────────────────
function formatDate($date) {
    if (!$date) return 'Unknown';
    return date('F j, Y', strtotime($date));
}

function formatYear($date) {
    if (!$date) return '?';
    return date('Y', strtotime($date));
}

// ── Quarters ──────────────────────────────────
function getQuarterName($pdo, $quarter_id) {
    $stmt = $pdo->prepare(
        "SELECT name FROM quarters
         WHERE quarter_id = ?"
    );
    $stmt->execute([$quarter_id]);
    $row = $stmt->fetch();
    return $row ? $row['name'] : 'Unknown Quarter';
}

function getAllQuarters($pdo) {
    return $pdo->query(
        "SELECT * FROM quarters ORDER BY name"
    )->fetchAll();
}

// ── Messages ──────────────────────────────────
function unreadCount($pdo, $user_id) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM messages
         WHERE receiver_id = ?
         AND is_read = 0"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// ── Profile checks ────────────────────────────
function hasProfile($pdo, $user_id) {
    $stmt = $pdo->prepare(
        "SELECT member_id FROM users
         WHERE user_id = ?
         AND member_id IS NOT NULL"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() !== false;
}

function getUserMember($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT
            fm.*,
            COALESCE(
                q.name,
                fm.village_of_origin,
                'Unknown'
            ) AS quarter_name
        FROM users u
        JOIN family_members fm
            ON u.member_id = fm.member_id
        LEFT JOIN quarters q
            ON fm.quarter_id = q.quarter_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// ── Connections ───────────────────────────────
// Returns each connected person ONCE
// from the perspective of member_id
// using relation_label for display
function getConnections($pdo, $member_id) {
    $stmt = $pdo->prepare("
        SELECT
            fm.*,
            COALESCE(
                q.name,
                fm.village_of_origin,
                'Unknown Village'
            ) AS quarter_name,
            r.type           AS relation_type,
            r.relation_label AS relation_label
        FROM relationships r
        JOIN family_members fm
            ON r.member_id_2 = fm.member_id
        LEFT JOIN quarters q
            ON fm.quarter_id = q.quarter_id
        WHERE r.member_id_1 = ?
        AND fm.member_id != ?
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$member_id, $member_id]);
    return $stmt->fetchAll();
}

// ── User tree ─────────────────────────────────
function getUserTree($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT
            fm.*,
            COALESCE(
                q.name,
                fm.village_of_origin,
                'Unknown Village'
            ) AS quarter_name
        FROM family_members fm
        LEFT JOIN quarters q
            ON fm.quarter_id = q.quarter_id
        LEFT JOIN users u
            ON u.member_id = fm.member_id
        WHERE fm.added_by = ?
           OR u.user_id   = ?
        ORDER BY fm.created_at ASC
    ");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll();
}

// ── Relationship labels ───────────────────────
function getRelationLabel($label, $type) {
    if (!$label) {
        return ucfirst($type);
    }
    $labels = [
        'father'               => 'Father',
        'mother'               => 'Mother',
        'son'                  => 'Son',
        'daughter'             => 'Daughter',
        'brother'              => 'Brother',
        'sister'               => 'Sister',
        'spouse'               => 'Spouse',
        'grandfather_paternal' =>
            'Grandfather (Father\'s side)',
        'grandmother_paternal' =>
            'Grandmother (Father\'s side)',
        'grandfather_maternal' =>
            'Grandfather (Mother\'s side)',
        'grandmother_maternal' =>
            'Grandmother (Mother\'s side)',
        'great_grandfather'    => 'Great Grandfather',
        'great_grandmother'    => 'Great Grandmother',
        'uncle'                => 'Uncle',
        'aunt'                 => 'Aunt',
        'nephew'               => 'Nephew',
        'niece'                => 'Niece',
        'cousin'               => 'Cousin',
        'stepfather'           => 'Stepfather',
        'stepmother'           => 'Stepmother',
        'half_brother'         => 'Half Brother',
        'half_sister'          => 'Half Sister',
        'other'                => 'Relative',
        // Reverse labels
        'son_or_daughter'      => 'Son / Daughter',
        'father_or_mother'     => 'Parent',
        'grandchild'           => 'Grandchild',
        'great_grandchild'     => 'Great Grandchild',
        'stepchild'            => 'Stepchild',
        'uncle_or_aunt'        => 'Uncle / Aunt',
        'nephew_or_niece'      => 'Nephew / Niece',
        'sibling'              => 'Sibling',
        'relative'             => 'Relative',
    ];
    return $labels[$label] ?? ucfirst(
        str_replace('_', ' ', $label)
    );
}

// ── Save relationship ─────────────────────────
function saveRelationship(
    $pdo,
    $member_id_1,
    $member_id_2,
    $type,
    $label = null
) {
    $reverse_type = [
        'parent'  => 'child',
        'child'   => 'parent',
        'spouse'  => 'spouse',
        'sibling' => 'sibling',
    ];

    $reverse_labels = [
        'father'               => 'son_or_daughter',
        'mother'               => 'son_or_daughter',
        'son'                  => 'father_or_mother',
        'daughter'             => 'father_or_mother',
        'grandfather_paternal' => 'grandchild',
        'grandmother_paternal' => 'grandchild',
        'grandfather_maternal' => 'grandchild',
        'grandmother_maternal' => 'grandchild',
        'great_grandfather'    => 'great_grandchild',
        'great_grandmother'    => 'great_grandchild',
        'stepfather'           => 'stepchild',
        'stepmother'           => 'stepchild',
        'nephew'               => 'uncle_or_aunt',
        'niece'                => 'uncle_or_aunt',
        'uncle'                => 'nephew_or_niece',
        'aunt'                 => 'nephew_or_niece',
        'spouse'               => 'spouse',
        'brother'              => 'sibling',
        'sister'               => 'sibling',
        'half_brother'         => 'sibling',
        'half_sister'          => 'sibling',
        'cousin'               => 'cousin',
        'other'                => 'relative',
        // reverse labels
        'son_or_daughter'      => 'father_or_mother',
        'father_or_mother'     => 'son_or_daughter',
        'grandchild'           => 'grandparent',
        'great_grandchild'     => 'great_grandparent',
        'stepchild'            => 'stepparent',
        'uncle_or_aunt'        => 'nephew_or_niece',
        'nephew_or_niece'      => 'uncle_or_aunt',
        'sibling'              => 'sibling',
        'cousin'               => 'cousin',
        'relative'             => 'relative',
    ];

    $r_type  = $reverse_type[$type]   ?? $type;
    $r_label = $reverse_labels[$label] ?? $r_type;

    // ── Save the direct relationship ──────────
    insertRelationshipIfNotExists(
        $pdo,
        $member_id_1, $member_id_2,
        $type, $label
    );

    insertRelationshipIfNotExists(
        $pdo,
        $member_id_2, $member_id_1,
        $r_type, $r_label
    );

    // ── Auto-infer chain relationships ────────
    // member_id_1 is always the ROOT user's node
    // member_id_2 is the new member being added
    autoInferRelationships(
        $pdo,
        $member_id_1, $member_id_2,
        $type, $label
    );
}

// ── Insert only if not already exists ─────────
function insertRelationshipIfNotExists(
    $pdo, $m1, $m2, $type, $label
) {
    $check = $pdo->prepare("
        SELECT relationship_id
        FROM relationships
        WHERE member_id_1 = ?
        AND   member_id_2 = ?
    ");
    $check->execute([$m1, $m2]);
    if ($check->fetch()) return;

    $pdo->prepare("
        INSERT INTO relationships
            (member_id_1, member_id_2,
             type, relation_label)
        VALUES (?, ?, ?, ?)
    ")->execute([$m1, $m2, $type, $label]);
}

// ── Auto-infer missing chain links ────────────
function autoInferRelationships(
    $pdo, $root_id, $new_member_id,
    $type, $label
) {
    // Rules: given what we know about
    // the new member's relation to root,
    // find other existing members and
    // create the missing links between them

    // ── Grandparent rules ─────────────────────
    // If new member is a grandfather/grandmother,
    // find the matching parent and connect them
    $grandparent_labels = [
        'grandfather_paternal',
        'grandmother_paternal',
    ];
    $maternal_labels = [
        'grandfather_maternal',
        'grandmother_maternal',
    ];

    if (in_array($label, $grandparent_labels)) {
        // Find root's father
        $parent = findRelativeByLabel(
            $pdo, $root_id, 'father'
        );
        if ($parent) {
            // New grandparent is parent's parent
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $parent,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $parent, $new_member_id,
                'child', 'son_or_daughter'
            );
        }
    }

    if (in_array($label, $maternal_labels)) {
        // Find root's mother
        $parent = findRelativeByLabel(
            $pdo, $root_id, 'mother'
        );
        if ($parent) {
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $parent,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $parent, $new_member_id,
                'child', 'son_or_daughter'
            );
        }
    }

    // ── Father/Mother rules ───────────────────
    // If new member is father, find existing
    // paternal grandparents and connect them
    if ($label === 'father') {
        $gf = findRelativeByLabel(
            $pdo, $root_id,
            'grandfather_paternal'
        );
        if ($gf) {
            insertRelationshipIfNotExists(
                $pdo,
                $gf, $new_member_id,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $gf,
                'child', 'son_or_daughter'
            );
        }
        $gm = findRelativeByLabel(
            $pdo, $root_id,
            'grandmother_paternal'
        );
        if ($gm) {
            insertRelationshipIfNotExists(
                $pdo,
                $gm, $new_member_id,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $gm,
                'child', 'son_or_daughter'
            );
        }

        // Also find root's siblings and
        // connect them to new father too
        $siblings = findRelativesByType(
            $pdo, $root_id, 'sibling'
        );
        foreach ($siblings as $sib) {
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $sib,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $sib, $new_member_id,
                'child', 'son_or_daughter'
            );
        }
    }

    if ($label === 'mother') {
        $gf = findRelativeByLabel(
            $pdo, $root_id,
            'grandfather_maternal'
        );
        if ($gf) {
            insertRelationshipIfNotExists(
                $pdo,
                $gf, $new_member_id,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $gf,
                'child', 'son_or_daughter'
            );
        }
        $gm = findRelativeByLabel(
            $pdo, $root_id,
            'grandmother_maternal'
        );
        if ($gm) {
            insertRelationshipIfNotExists(
                $pdo,
                $gm, $new_member_id,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $gm,
                'child', 'son_or_daughter'
            );
        }

        // Siblings → mother
        $siblings = findRelativesByType(
            $pdo, $root_id, 'sibling'
        );
        foreach ($siblings as $sib) {
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $sib,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $sib, $new_member_id,
                'child', 'son_or_daughter'
            );
        }
    }

    // ── Sibling rules ─────────────────────────
    // If new member is a sibling,
    // connect them to existing parents
    if (in_array($label, [
        'brother', 'sister',
        'half_brother', 'half_sister'
    ])) {
        $father = findRelativeByLabel(
            $pdo, $root_id, 'father'
        );
        if ($father) {
            insertRelationshipIfNotExists(
                $pdo,
                $father, $new_member_id,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $father,
                'child', 'son_or_daughter'
            );
        }

        $mother = findRelativeByLabel(
            $pdo, $root_id, 'mother'
        );
        if ($mother) {
            insertRelationshipIfNotExists(
                $pdo,
                $mother, $new_member_id,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $mother,
                'child', 'son_or_daughter'
            );
        }
    }

    // ── Spouse rules ──────────────────────────
    // If new member is spouse, connect
    // them to root's children as parent
    if ($label === 'spouse') {
        $children = findRelativesByType(
            $pdo, $root_id, 'child'
        );
        foreach ($children as $child) {
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $child,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $child, $new_member_id,
                'child', 'son_or_daughter'
            );
        }
    }

    // ── Son/Daughter rules ────────────────────
    // If new member is a child,
    // connect them to existing spouse
    if (in_array($label, ['son', 'daughter'])) {
        $spouse = findRelativeByLabel(
            $pdo, $root_id, 'spouse'
        );
        if ($spouse) {
            insertRelationshipIfNotExists(
                $pdo,
                $spouse, $new_member_id,
                'parent', 'father_or_mother'
            );
            insertRelationshipIfNotExists(
                $pdo,
                $new_member_id, $spouse,
                'child', 'son_or_daughter'
            );
        }
    }
}

// ── Helper: find a relative by specific label ─
function findRelativeByLabel(
    $pdo, $root_id, $label
) {
    $stmt = $pdo->prepare("
        SELECT member_id_2
        FROM relationships
        WHERE member_id_1    = ?
        AND   relation_label = ?
        LIMIT 1
    ");
    $stmt->execute([$root_id, $label]);
    $row = $stmt->fetch();
    return $row ? (int)$row['member_id_2'] : null;
}

// ── Helper: find relatives by type ────────────
function findRelativesByType(
    $pdo, $root_id, $type
) {
    $stmt = $pdo->prepare("
        SELECT member_id_2
        FROM relationships
        WHERE member_id_1 = ?
        AND   type        = ?
    ");
    $stmt->execute([$root_id, $type]);
    return array_column(
        $stmt->fetchAll(), 'member_id_2'
    );
}

// ── File upload ───────────────────────────────
function uploadFile($file, $type) {
    $allowed = [
        'photo'    => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'document' => [
            'application/pdf',
        ],
        'audio'    => [
            'audio/mpeg',
            'audio/wav',
            'audio/mp4',
        ],
    ];

    if (!isset($allowed[$type]))
        return ['error' => 'Invalid file type'];

    if (!in_array($file['type'], $allowed[$type]))
        return ['error' => 'File format not allowed'];

    if ($file['size'] > MAX_FILE_SIZE)
        return ['error' => 'File exceeds 10MB limit'];

    $ext      = pathinfo(
        $file['name'], PATHINFO_EXTENSION
    );
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $folder   = UPLOAD_PATH . $type . 's/';
    $path     = $folder . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path))
        return ['error' => 'Upload failed'];

    return [
        'path'     => 'uploads/'
                      . $type . 's/' . $filename,
        'filename' => $filename,
        'size'     => $file['size'],
        'mime'     => $file['type'],
    ];
}