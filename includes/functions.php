<?php

// ── CSRF Protection ────────────────────────────
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden"
        name="csrf_token"
        value="' . csrfToken() . '">';
}

function csrfVerify() {
    $token = $_POST['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? '';

    if (!isset($_SESSION['csrf_token'])
        || empty($token)
        || !hash_equals(
            $_SESSION['csrf_token'], $token
        )
    ) {
        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));

        $redirect = $_SERVER['HTTP_REFERER']
            ?? (defined('SITE_URL')
                ? SITE_URL . '/dashboard.php'
                : '/');

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept,
            'application/json')) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode([
                'error' => 'Session expired. '
                    . 'Please refresh and try again.'
            ]));
        }

        $sep = str_contains($redirect, '?')
            ? '&' : '?';
        header('Location: '
            . $redirect . $sep . 'csrf_error=1');
        exit;
    }
}

function csrfVerifyApi() {
    $token = $_POST['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? '';
    return isset($_SESSION['csrf_token'])
        && !empty($token)
        && hash_equals(
            $_SESSION['csrf_token'], $token
        );
}

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
function getRelationLabel(
    $label, $type, $gender = null
) {
    if (!$label) return ucfirst($type);

    $male_overrides = [
        'son_or_daughter'   => 'Son',
        'father_or_mother'  => 'Father',
        'uncle_or_aunt'     => 'Uncle',
        'nephew_or_niece'   => 'Nephew',
        'grandparent'       => 'Grandfather',
        'great_grandparent' => 'Great Grandfather',
        'stepparent'        => 'Stepfather',
        'stepchild'         => 'Stepson',
        'sibling'           => 'Brother',
        'brother'           => 'Brother',
        'sister'            => 'Brother',
        'grandchild'        => 'Grandson',
        'cousin'            => 'Cousin',
        'relative'          => 'Relative',
    ];

    $female_overrides = [
        'son_or_daughter'   => 'Daughter',
        'father_or_mother'  => 'Mother',
        'uncle_or_aunt'     => 'Aunt',
        'nephew_or_niece'   => 'Niece',
        'grandparent'       => 'Grandmother',
        'great_grandparent' => 'Great Grandmother',
        'stepparent'        => 'Stepmother',
        'stepchild'         => 'Stepdaughter',
        'sibling'           => 'Sister',
        'brother'           => 'Sister',
        'sister'            => 'Sister',
        'grandchild'        => 'Granddaughter',
        'cousin'            => 'Cousin',
        'relative'          => 'Relative',
    ];

    $labels = [
        'father'               => 'Father',
        'mother'               => 'Mother',
        'son'                  => 'Son',
        'daughter'             => 'Daughter',
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
        'son_or_daughter'      => 'Son / Daughter',
        'father_or_mother'     => 'Parent',
        'grandchild'           => 'Grandchild',
        'great_grandchild'     => 'Great Grandchild',
        'stepchild'            => 'Stepchild',
        'uncle_or_aunt'        => 'Uncle / Aunt',
        'nephew_or_niece'      => 'Nephew / Niece',
        'sibling'              => 'Sibling',
        'grandparent'          => 'Grandparent',
        'great_grandparent'    => 'Great Grandparent',
        'stepparent'           => 'Stepparent',
        'relative'             => 'Relative',
    ];

    $result = isset($labels[$label])
        ? $labels[$label]
        : ucfirst(str_replace('_', ' ', $label));

    $gender_dependent = [
        'son_or_daughter', 'father_or_mother',
        'uncle_or_aunt',   'nephew_or_niece',
        'grandparent',     'great_grandparent',
        'stepparent',      'stepchild',
        'sibling',         'brother',
        'sister',          'cousin',
        'relative',        'grandchild',
    ];

    if (in_array($label, $gender_dependent)
        && $gender !== null) {
        if ($gender === 'male'
            && isset($male_overrides[$label])) {
            $result = $male_overrides[$label];
        } elseif ($gender === 'female'
            && isset($female_overrides[$label])) {
            $result = $female_overrides[$label];
        }
    }

    return $result;
}

// ── Save relationship ─────────────────────────
function saveRelationship(
    $pdo, $source_id, $target_id,
    $type, $label = null
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

    $r_type  = $reverse_type[$type]    ?? $type;
    $r_label = $reverse_labels[$label] ?? $r_type;

    insertRelIfNotExists(
        $pdo, $source_id, $target_id,
        $type, $label
    );
    insertRelIfNotExists(
        $pdo, $target_id, $source_id,
        $r_type, $r_label
    );

    autoInfer(
        $pdo, $source_id, $target_id,
        $type, $label
    );
}

// ── Insert only if not already exists ─────────
function insertRelIfNotExists(
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

function insertRelationshipIfNotExists(
    $pdo, $m1, $m2, $type, $label
) {
    insertRelIfNotExists(
        $pdo, $m1, $m2, $type, $label
    );
}

// ── Auto-infer chain relationships ────────────
function autoInfer(
    $pdo, $source_id, $target_id,
    $type, $label
) {

    // ── Paternal Grandparent added ────────────
    if ($label === 'grandfather_paternal'
        || $label === 'grandmother_paternal') {

        // Connect grandparent → father
        $father = findByLabel(
            $pdo, $source_id, 'father'
        );
        if ($father) {
            // grandparent IS the parent of father
            insertRelIfNotExists(
                $pdo, $target_id, $father,
                'parent', 'son_or_daughter'
            );
            // father IS the child of grandparent
            insertRelIfNotExists(
                $pdo, $father, $target_id,
                'child', $label
            );

            // Connect grandparent to all of
            // father's OTHER children (source's
            // siblings) as grandchildren
            $fathers_children = $pdo->prepare("
                SELECT member_id_1
                FROM relationships
                WHERE member_id_2    = ?
                  AND type           = 'child'
                  AND relation_label IN (
                      'father','mother',
                      'stepfather','stepmother')
                  AND member_id_1   != ?
            ");
            $fathers_children->execute([
                $father, $source_id
            ]);
            foreach (
                $fathers_children->fetchAll(
                    PDO::FETCH_COLUMN
                ) as $sib_id
            ) {
                insertRelIfNotExists(
                    $pdo, $target_id, $sib_id,
                    'parent', 'grandchild'
                );
                insertRelIfNotExists(
                    $pdo, $sib_id, $target_id,
                    'child', $label
                );
            }

            // Connect to the other paternal
            // grandparent as spouse
            $other_label =
                $label === 'grandfather_paternal'
                ? 'grandmother_paternal'
                : 'grandfather_paternal';
            $other_gp = findByLabel(
                $pdo, $source_id, $other_label
            );
            if ($other_gp) {
                insertRelIfNotExists(
                    $pdo, $target_id, $other_gp,
                    'spouse', 'spouse'
                );
                insertRelIfNotExists(
                    $pdo, $other_gp, $target_id,
                    'spouse', 'spouse'
                );
            }

            // Find grandparent's OTHER children
            // (not the father) → they are uncles/
            // aunts to source
            $gp_other_children = $pdo->prepare("
                SELECT member_id_1
                FROM   relationships
                WHERE  member_id_2    = ?
                  AND  type           = 'child'
                  AND  relation_label IN (
                      'father','mother',
                      'stepfather','stepmother')
                  AND  member_id_1   != ?
            ");
            $gp_other_children->execute([
                $target_id, $father
            ]);
            foreach (
                $gp_other_children->fetchAll(
                    PDO::FETCH_COLUMN
                ) as $uncle_id
            ) {
                // uncle/aunt → source
                insertRelIfNotExists(
                    $pdo, $uncle_id, $source_id,
                    'parent', 'nephew_or_niece'
                );
                insertRelIfNotExists(
                    $pdo, $source_id, $uncle_id,
                    'child', 'uncle_or_aunt'
                );
                // also link uncle to source's siblings
                foreach (
                    $fathers_children->fetchAll(
                        PDO::FETCH_COLUMN
                    ) as $sib_id
                ) {
                    insertRelIfNotExists(
                        $pdo, $uncle_id, $sib_id,
                        'parent', 'nephew_or_niece'
                    );
                    insertRelIfNotExists(
                        $pdo, $sib_id, $uncle_id,
                        'child', 'uncle_or_aunt'
                    );
                }
            }
        }

        // Connect grandparent → source as grandchild
        insertRelIfNotExists(
            $pdo, $target_id, $source_id,
            'parent', 'grandchild'
        );
        insertRelIfNotExists(
            $pdo, $source_id, $target_id,
            'child', $label
        );
    }

    // ── Maternal Grandparent added ────────────
    if ($label === 'grandfather_maternal'
        || $label === 'grandmother_maternal') {

        $mother = findByLabel(
            $pdo, $source_id, 'mother'
        );
        if ($mother) {
            insertRelIfNotExists(
                $pdo, $target_id, $mother,
                'parent', 'son_or_daughter'
            );
            insertRelIfNotExists(
                $pdo, $mother, $target_id,
                'child', $label
            );

            $mothers_children = $pdo->prepare("
                SELECT member_id_1
                FROM relationships
                WHERE member_id_2    = ?
                  AND type           = 'child'
                  AND relation_label IN (
                      'father','mother',
                      'stepfather','stepmother')
                  AND member_id_1   != ?
            ");
            $mothers_children->execute([
                $mother, $source_id
            ]);
            foreach (
                $mothers_children->fetchAll(
                    PDO::FETCH_COLUMN
                ) as $sib_id
            ) {
                insertRelIfNotExists(
                    $pdo, $target_id, $sib_id,
                    'parent', 'grandchild'
                );
                insertRelIfNotExists(
                    $pdo, $sib_id, $target_id,
                    'child', $label
                );
            }

            $other_label =
                $label === 'grandfather_maternal'
                ? 'grandmother_maternal'
                : 'grandfather_maternal';
            $other_gp = findByLabel(
                $pdo, $source_id, $other_label
            );
            if ($other_gp) {
                insertRelIfNotExists(
                    $pdo, $target_id, $other_gp,
                    'spouse', 'spouse'
                );
                insertRelIfNotExists(
                    $pdo, $other_gp, $target_id,
                    'spouse', 'spouse'
                );
            }

            // Find maternal grandparent's OTHER
            // children → maternal uncles/aunts
            $mgp_other_children = $pdo->prepare("
                SELECT member_id_1
                FROM   relationships
                WHERE  member_id_2    = ?
                  AND  type           = 'child'
                  AND  relation_label IN (
                      'father','mother',
                      'stepfather','stepmother')
                  AND  member_id_1   != ?
            ");
            $mgp_other_children->execute([
                $target_id, $mother
            ]);
            foreach (
                $mgp_other_children->fetchAll(
                    PDO::FETCH_COLUMN
                ) as $uncle_id
            ) {
                insertRelIfNotExists(
                    $pdo, $uncle_id, $source_id,
                    'parent', 'nephew_or_niece'
                );
                insertRelIfNotExists(
                    $pdo, $source_id, $uncle_id,
                    'child', 'uncle_or_aunt'
                );
            }
        }

        insertRelIfNotExists(
            $pdo, $target_id, $source_id,
            'parent', 'grandchild'
        );
        insertRelIfNotExists(
            $pdo, $source_id, $target_id,
            'child', $label
        );
    }

    // ── Father added ──────────────────────────
    if ($label === 'father') {

        // Connect father → source's paternal
        // grandparents (they are his parents)
        $gf = findByLabel(
            $pdo, $source_id,
            'grandfather_paternal'
        );
        if ($gf) {
            insertRelIfNotExists(
                $pdo, $gf, $target_id,
                'parent', 'son_or_daughter'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gf,
                'child', 'father'
            );
        }

        $gm = findByLabel(
            $pdo, $source_id,
            'grandmother_paternal'
        );
        if ($gm) {
            insertRelIfNotExists(
                $pdo, $gm, $target_id,
                'parent', 'son_or_daughter'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gm,
                'child', 'mother'
            );
        }

        // Connect source's existing siblings
        // to the new father
        $siblings = findByType(
            $pdo, $source_id, 'sibling'
        );
        foreach ($siblings as $sib) {
            insertRelIfNotExists(
                $pdo, $target_id, $sib,
                'parent', 'father_or_mother'
            );
            insertRelIfNotExists(
                $pdo, $sib, $target_id,
                'child', 'son_or_daughter'
            );
        }

        // Find father's DIRECT existing children
        // and make them siblings of source.
        // CRITICAL: only use label='father'/'mother'
        // to avoid linking grandchildren as siblings
        $existing_children = $pdo->prepare("
            SELECT member_id_1
            FROM   relationships
            WHERE  member_id_2    = ?
              AND  type           = 'child'
              AND  relation_label IN (
                  'father','mother',
                  'stepfather','stepmother')
              AND  member_id_1   != ?
        ");
        $existing_children->execute([
            $target_id, $source_id
        ]);
        foreach (
            $existing_children->fetchAll(
                PDO::FETCH_COLUMN
            ) as $sibling_id
        ) {
            insertRelIfNotExists(
                $pdo, $source_id, $sibling_id,
                'sibling', 'sibling'
            );
            insertRelIfNotExists(
                $pdo, $sibling_id, $source_id,
                'sibling', 'sibling'
            );
        }

        // Find father's existing spouse and
        // link as source's mother
        $fathers_spouse = findByLabel(
            $pdo, $target_id, 'spouse'
        );
        if ($fathers_spouse) {
            insertRelIfNotExists(
                $pdo, $source_id, $fathers_spouse,
                'child', 'mother'
            );
            insertRelIfNotExists(
                $pdo, $fathers_spouse, $source_id,
                'parent', 'son_or_daughter'
            );
        }

        // Find all new siblings (father's direct
        // children just linked as siblings of source)
        // then find THEIR children and link as
        // nephew/niece to source
        $new_siblings = $pdo->prepare("
            SELECT member_id_1
            FROM   relationships
            WHERE  member_id_2    = ?
              AND  type           = 'child'
              AND  relation_label IN (
                  'father','mother',
                  'stepfather','stepmother')
              AND  member_id_1   != ?
        ");
        $new_siblings->execute([
            $target_id, $source_id
        ]);
        foreach (
            $new_siblings->fetchAll(
                PDO::FETCH_COLUMN
            ) as $sib_id
        ) {
            // Find each sibling's children
            $sibs_children = findByType(
                $pdo, $sib_id, 'parent'
            );
            foreach ($sibs_children as $nephew_id) {
                // source → nephew/niece
                insertRelIfNotExists(
                    $pdo, $source_id, $nephew_id,
                    'parent', 'nephew_or_niece'
                );
                // nephew/niece → source (uncle)
                insertRelIfNotExists(
                    $pdo, $nephew_id, $source_id,
                    'child', 'uncle_or_aunt'
                );
            }
        }

        // Also find father's own siblings
        // (source's uncles/aunts) and link them
        $fathers_siblings = findByType(
            $pdo, $target_id, 'sibling'
        );
        foreach ($fathers_siblings as $uncle_id) {
            insertRelIfNotExists(
                $pdo, $uncle_id, $source_id,
                'parent', 'nephew_or_niece'
            );
            insertRelIfNotExists(
                $pdo, $source_id, $uncle_id,
                'child', 'uncle_or_aunt'
            );
        }
    }

    // ── Mother added ──────────────────────────
    if ($label === 'mother') {

        $gf = findByLabel(
            $pdo, $source_id,
            'grandfather_maternal'
        );
        if ($gf) {
            insertRelIfNotExists(
                $pdo, $gf, $target_id,
                'parent', 'son_or_daughter'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gf,
                'child', 'father'
            );
        }

        $gm = findByLabel(
            $pdo, $source_id,
            'grandmother_maternal'
        );
        if ($gm) {
            insertRelIfNotExists(
                $pdo, $gm, $target_id,
                'parent', 'son_or_daughter'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gm,
                'child', 'mother'
            );
        }

        // Find mother's existing spouse and
        // link as source's father
        $mothers_spouse = findByLabel(
            $pdo, $target_id, 'spouse'
        );
        if ($mothers_spouse) {
            insertRelIfNotExists(
                $pdo, $source_id, $mothers_spouse,
                'child', 'father'
            );
            insertRelIfNotExists(
                $pdo, $mothers_spouse, $source_id,
                'parent', 'son_or_daughter'
            );
        }

        // Connect source's siblings to mother
        $siblings = findByType(
            $pdo, $source_id, 'sibling'
        );
        foreach ($siblings as $sib) {
            insertRelIfNotExists(
                $pdo, $target_id, $sib,
                'parent', 'father_or_mother'
            );
            insertRelIfNotExists(
                $pdo, $sib, $target_id,
                'child', 'son_or_daughter'
            );
        }

        // Find mother's DIRECT existing children
        // only — not grandchildren
        $existing_children = $pdo->prepare("
            SELECT member_id_1
            FROM   relationships
            WHERE  member_id_2    = ?
              AND  type           = 'child'
              AND  relation_label IN (
                  'father','mother',
                  'stepfather','stepmother')
              AND  member_id_1   != ?
        ");
        $existing_children->execute([
            $target_id, $source_id
        ]);
        foreach (
            $existing_children->fetchAll(
                PDO::FETCH_COLUMN
            ) as $sibling_id
        ) {
            insertRelIfNotExists(
                $pdo, $source_id, $sibling_id,
                'sibling', 'sibling'
            );
            insertRelIfNotExists(
                $pdo, $sibling_id, $source_id,
                'sibling', 'sibling'
            );
        }

        // Find mother's siblings' children
        // and link as nephew/niece to source
        $new_siblings_m = $pdo->prepare("
            SELECT member_id_1
            FROM   relationships
            WHERE  member_id_2    = ?
              AND  type           = 'child'
              AND  relation_label IN (
                  'father','mother',
                  'stepfather','stepmother')
              AND  member_id_1   != ?
        ");
        $new_siblings_m->execute([
            $target_id, $source_id
        ]);
        foreach (
            $new_siblings_m->fetchAll(
                PDO::FETCH_COLUMN
            ) as $sib_id
        ) {
            $sibs_children = findByType(
                $pdo, $sib_id, 'parent'
            );
            foreach ($sibs_children as $nephew_id) {
                insertRelIfNotExists(
                    $pdo, $source_id, $nephew_id,
                    'parent', 'nephew_or_niece'
                );
                insertRelIfNotExists(
                    $pdo, $nephew_id, $source_id,
                    'child', 'uncle_or_aunt'
                );
            }
        }

        // Mother's own siblings → source's
        // maternal uncles/aunts
        $mothers_siblings = findByType(
            $pdo, $target_id, 'sibling'
        );
        foreach ($mothers_siblings as $uncle_id) {
            insertRelIfNotExists(
                $pdo, $uncle_id, $source_id,
                'parent', 'nephew_or_niece'
            );
            insertRelIfNotExists(
                $pdo, $source_id, $uncle_id,
                'child', 'uncle_or_aunt'
            );
        }
    }

    // ── Sibling added ─────────────────────────
    if (in_array($label, [
        'brother', 'sister',
        'half_brother', 'half_sister'
    ])) {
        // Connect to source's parents
        $father = findByLabel(
            $pdo, $source_id, 'father'
        );
        if ($father) {
            insertRelIfNotExists(
                $pdo, $father, $target_id,
                'parent', 'father_or_mother'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $father,
                'child', 'son_or_daughter'
            );
        }

        $mother = findByLabel(
            $pdo, $source_id, 'mother'
        );
        if ($mother) {
            insertRelIfNotExists(
                $pdo, $mother, $target_id,
                'parent', 'father_or_mother'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $mother,
                'child', 'son_or_daughter'
            );
        }

        // Connect to paternal grandparents
        $gf_pat = findByLabel(
            $pdo, $source_id,
            'grandfather_paternal'
        );
        if ($gf_pat) {
            insertRelIfNotExists(
                $pdo, $gf_pat, $target_id,
                'parent', 'grandchild'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gf_pat,
                'child', 'grandfather_paternal'
            );
        }

        $gm_pat = findByLabel(
            $pdo, $source_id,
            'grandmother_paternal'
        );
        if ($gm_pat) {
            insertRelIfNotExists(
                $pdo, $gm_pat, $target_id,
                'parent', 'grandchild'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gm_pat,
                'child', 'grandmother_paternal'
            );
        }

        // Connect to maternal grandparents
        $gf_mat = findByLabel(
            $pdo, $source_id,
            'grandfather_maternal'
        );
        if ($gf_mat) {
            insertRelIfNotExists(
                $pdo, $gf_mat, $target_id,
                'parent', 'grandchild'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gf_mat,
                'child', 'grandfather_maternal'
            );
        }

        $gm_mat = findByLabel(
            $pdo, $source_id,
            'grandmother_maternal'
        );
        if ($gm_mat) {
            insertRelIfNotExists(
                $pdo, $gm_mat, $target_id,
                'parent', 'grandchild'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gm_mat,
                'child', 'grandmother_maternal'
            );
        }

        // Connect new sibling to all existing
        // siblings of source
        $existing_siblings = findByType(
            $pdo, $source_id, 'sibling'
        );
        foreach ($existing_siblings as $sib) {
            if ($sib === $target_id) continue;
            insertRelIfNotExists(
                $pdo, $target_id, $sib,
                'sibling', 'sibling'
            );
            insertRelIfNotExists(
                $pdo, $sib, $target_id,
                'sibling', 'sibling'
            );
        }

        // Find new sibling's existing children
        // and link them as nephew/niece to source
        $sibs_children = findByType(
            $pdo, $target_id, 'parent'
        );
        foreach ($sibs_children as $nephew_id) {
            insertRelIfNotExists(
                $pdo, $source_id, $nephew_id,
                'parent', 'nephew_or_niece'
            );
            insertRelIfNotExists(
                $pdo, $nephew_id, $source_id,
                'child', 'uncle_or_aunt'
            );
        }

        // Cross-link via shared parents:
        // find all other DIRECT children of
        // source's parents and link as siblings.
        // This handles the case where two users
        // independently add the same parent.
        $source_parents = $pdo->prepare("
            SELECT member_id_2
            FROM   relationships
            WHERE  member_id_1 = ?
              AND  type        = 'child'
        ");
        $source_parents->execute([$source_id]);
        $parent_ids = array_column(
            $source_parents->fetchAll(),
            'member_id_2'
        );

        foreach ($parent_ids as $parent_id) {
            $parent_children = $pdo->prepare("
                SELECT member_id_1
                FROM   relationships
                WHERE  member_id_2    = ?
                  AND  type           = 'child'
                  AND  relation_label IN (
                      'father','mother',
                      'stepfather','stepmother')
                  AND  member_id_1   != ?
                  AND  member_id_1   != ?
            ");
            $parent_children->execute([
                $parent_id,
                $source_id,
                $target_id,
            ]);
            foreach (
                $parent_children->fetchAll(
                    PDO::FETCH_COLUMN
                ) as $half_sib_id
            ) {
                insertRelIfNotExists(
                    $pdo, $target_id, $half_sib_id,
                    'sibling', 'sibling'
                );
                insertRelIfNotExists(
                    $pdo, $half_sib_id, $target_id,
                    'sibling', 'sibling'
                );
            }
        }
    }

    // ── Son/Daughter added ────────────────────
    if (in_array($label, ['son', 'daughter'])) {

        // Connect to source's spouse
        $spouse = findByLabel(
            $pdo, $source_id, 'spouse'
        );
        if ($spouse) {
            insertRelIfNotExists(
                $pdo, $spouse, $target_id,
                'parent', 'father_or_mother'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $spouse,
                'child', 'son_or_daughter'
            );
        }

        // Connect to source's father
        // (child's paternal grandfather)
        $gf = findByLabel(
            $pdo, $source_id, 'father'
        );
        if ($gf) {
            insertRelIfNotExists(
                $pdo, $gf, $target_id,
                'parent', 'grandchild'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gf,
                'child', 'grandfather_paternal'
            );
        }

        // Connect to source's mother
        // (child's paternal grandmother)
        $gm = findByLabel(
            $pdo, $source_id, 'mother'
        );
        if ($gm) {
            insertRelIfNotExists(
                $pdo, $gm, $target_id,
                'parent', 'grandchild'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $gm,
                'child', 'grandmother_paternal'
            );
        }

        // Make siblings with existing children
        $existing = findByType(
            $pdo, $source_id, 'parent'
        );
        foreach ($existing as $sib_id) {
            if ($sib_id === $target_id) continue;
            insertRelIfNotExists(
                $pdo, $target_id, $sib_id,
                'sibling', 'sibling'
            );
            insertRelIfNotExists(
                $pdo, $sib_id, $target_id,
                'sibling', 'sibling'
            );
        }

        // Find source's siblings → they are
        // uncle/aunt to the new child
        $source_siblings = findByType(
            $pdo, $source_id, 'sibling'
        );
        foreach ($source_siblings as $uncle_id) {
            insertRelIfNotExists(
                $pdo, $uncle_id, $target_id,
                'parent', 'nephew_or_niece'
            );
            insertRelIfNotExists(
                $pdo, $target_id, $uncle_id,
                'child', 'uncle_or_aunt'
            );
        }

        // Find spouse's siblings → also
        // uncle/aunt to the new child
        if (isset($spouse) && $spouse) {
            $spouse_siblings = findByType(
                $pdo, $spouse, 'sibling'
            );
            foreach ($spouse_siblings
                     as $uncle_id) {
                insertRelIfNotExists(
                    $pdo, $uncle_id, $target_id,
                    'parent', 'nephew_or_niece'
                );
                insertRelIfNotExists(
                    $pdo, $target_id, $uncle_id,
                    'child', 'uncle_or_aunt'
                );
            }
        }
    }

    // ── Spouse added ──────────────────────────
    if ($label === 'spouse') {
        // Connect spouse to source's children
        $children = findByType(
            $pdo, $source_id, 'parent'
        );
        foreach ($children as $child) {
            insertRelIfNotExists(
                $pdo, $target_id, $child,
                'parent', 'father_or_mother'
            );
            insertRelIfNotExists(
                $pdo, $child, $target_id,
                'child', 'son_or_daughter'
            );
        }
    }
}

// ── Find relative by specific label ──────────
function findByLabel($pdo, $member_id, $label) {
    $stmt = $pdo->prepare("
        SELECT member_id_2
        FROM relationships
        WHERE member_id_1    = ?
        AND   relation_label = ?
        LIMIT 1
    ");
    $stmt->execute([$member_id, $label]);
    $row = $stmt->fetch();
    return $row ? (int)$row['member_id_2'] : null;
}

function findRelativeByLabel(
    $pdo, $root_id, $label
) {
    return findByLabel($pdo, $root_id, $label);
}

// ── Find relatives by type ────────────────────
function findByType($pdo, $member_id, $type) {
    $stmt = $pdo->prepare("
        SELECT member_id_2
        FROM relationships
        WHERE member_id_1 = ?
        AND   type        = ?
    ");
    $stmt->execute([$member_id, $type]);
    return array_column(
        $stmt->fetchAll(), 'member_id_2'
    );
}

function findRelativesByType(
    $pdo, $root_id, $type
) {
    return findByType($pdo, $root_id, $type);
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
    $filename = uniqid() . '_' . time()
                . '.' . $ext;
    $folder   = UPLOAD_PATH . $type . 's/';
    $path     = $folder . $filename;

    if (!move_uploaded_file(
        $file['tmp_name'], $path
    )) return ['error' => 'Upload failed'];

    return [
        'path'     => 'uploads/'
                      . $type . 's/' . $filename,
        'filename' => $filename,
        'size'     => $file['size'],
        'mime'     => $file['type'],
    ];
}