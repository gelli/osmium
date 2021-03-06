<?php
/* Osmium
 * Copyright (C) 2012, 2013, 2014 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Osmium\Page\DeleteComment;

require __DIR__.'/../inc/root.php';

if(!\Osmium\State\is_logged_in() || !isset($_POST) || $_POST === []) {
	\Osmium\fatal(403);
}

$id = isset($_GET['id']) ? $_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';
$a = \Osmium\State\get_state('a');

if($type == 'comment') {
	$row = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT accountid, loadoutid FROM osmium.loadoutcomments WHERE commentid = $1',
		array($id)
	));

	if($row === false) {
		\Osmium\fatal(404);
	}

	if($row['accountid'] != $a['accountid'] && $a['ismoderator'] !== 't') {
		\Osmium\fatal(403);
	}

	\Osmium\Db\query('BEGIN;');

	$rq = \Osmium\Db\query_params(
		'SELECT commentreplyid, bodycontentid
		FROM osmium.loadoutcommentreplies
		WHERE commentid = $1',
		[ $id ]
	);
	while($rep = \Osmium\Db\fetch_assoc($rq)) {
		\Osmium\Db\query_params(
			'DELETE FROM osmium.loadoutcommentreplies WHERE commentreplyid = $1',
			[ $rep['commentreplyid'] ]
		);
		\Osmium\Db\query_params(
			'DELETE FROM osmium.editableformattedcontents WHERE contentid = $1',
			[ $rep['bodycontentid'] ]
		);
	}

	$rq = \Osmium\Db\query_params(
		'SELECT revision, bodycontentid
		FROM osmium.loadoutcommentrevisions
		WHERE commentid = $1',
		[ $id ]
	);
	while($rev = \Osmium\Db\fetch_assoc($rq)) {
		\Osmium\Db\query_params(
			'DELETE FROM osmium.loadoutcommentrevisions WHERE commentid = $1 AND revision = $2',
			[ $id, $rev['revision'] ]
		);
		\Osmium\Db\query_params(
			'DELETE FROM osmium.editableformattedcontents WHERE contentid = $1',
			[ $rev['bodycontentid'] ]
		);
	}

	\Osmium\Db\query_params('DELETE FROM osmium.loadoutcomments WHERE commentid = $1', [ $id ]);

	\Osmium\Reputation\nullify_votes(
		'targettype = $1 AND targetid1 = $2 AND targetid2 = $3 AND targetid3 IS NULL',
		array(
			\Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT,
			$id, $row['loadoutid'],
		),
		true
	);
	\Osmium\Log\add_log_entry(\Osmium\Log\LOG_TYPE_DELETE_COMMENT, null, $id, $row['loadoutid']);
	\Osmium\Db\query('COMMIT;');

	$afteruri = '#comments';
} else if($type == 'commentreply') {
	$row = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params(
		'SELECT lcr.accountid, lcr.commentid, loadoutid, lcr.bodycontentid
		FROM osmium.loadoutcommentreplies lcr
		JOIN osmium.loadoutcomments lc ON lc.commentid = lcr.commentid
		WHERE commentreplyid = $1',
		array($id)
	));

	if($row === false) {
		\Osmium\fatal(404);
	}

	if($row['accountid'] != $a['accountid'] && $a['ismoderator'] !== 't') {
		\Osmium\fatal(403);
	}

	\Osmium\Db\query('BEGIN;');

	\Osmium\Db\query_params(
		'DELETE FROM osmium.loadoutcommentreplies WHERE commentreplyid = $1',
		[ $id ]
	);

	\Osmium\Db\query_params(
		'DELETE FROM osmium.editableformattedcontents WHERE contentid = $1',
		[ $row['bodycontentid'] ]
	);

	\Osmium\Log\add_log_entry(
		\Osmium\Log\LOG_TYPE_DELETE_COMMENT_REPLY, null,
		$id, $row['commentid'], $row['loadoutid']
	);

	\Osmium\Db\query('COMMIT;');

	$afteruri = '?jtc='.$row['commentid'].'#c'.$row['commentid'];
} else {
	\Osmium\fatal(400);
}

header('Location: ../../'.\Osmium\Fit\fetch_fit_uri($row['loadoutid']).$afteruri);
die();
