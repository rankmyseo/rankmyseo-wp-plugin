#!/usr/bin/env node
/**
 * Extract @wordpress/i18n calls from TypeScript sources into a POT file.
 * wp i18n make-pot scans .js/.jsx only, so this covers src .ts/.tsx.
 */
import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';

const root = path.resolve( path.dirname( new URL( import.meta.url ).pathname ), '..' );
const srcDir = path.join( root, 'src' );
const outFile = path.join( root, 'languages', 'rankmyseo-js.pot' );
const functions = new Set( [ '__', '_x', '_n', '_nx' ] );

function walk( dir, files = [] ) {
	for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {
		const full = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			walk( full, files );
		} else if ( entry.name.endsWith( '.ts' ) || entry.name.endsWith( '.tsx' ) ) {
			files.push( full );
		}
	}
	return files;
}

function stringArg( node ) {
	if ( ! node ) {
		return null;
	}
	if ( ts.isStringLiteral( node ) || ts.isNoSubstitutionTemplateLiteral( node ) ) {
		return node.text;
	}
	return null;
}

const messages = new Map();

for ( const file of walk( srcDir ) ) {
	const text = fs.readFileSync( file, 'utf8' );
	const source = ts.createSourceFile( file, text, ts.ScriptTarget.Latest, true, file.endsWith( '.tsx' ) ? ts.ScriptKind.TSX : ts.ScriptKind.TS );
	const rel = path.relative( root, file ).split( path.sep ).join( '/' );

	function visit( node ) {
		if ( ts.isCallExpression( node ) && ts.isIdentifier( node.expression ) && functions.has( node.expression.text ) ) {
			const name = node.expression.text;
			const msgid = stringArg( node.arguments[ 0 ] );
			const domainIndex = name === '_x' || name === '_n' ? 2 : name === '_nx' ? 3 : 1;
			const domain = stringArg( node.arguments[ domainIndex ] );
			if ( msgid && domain === 'rankmyseo' ) {
				const { line } = source.getLineAndCharacterOfPosition( node.getStart( source ) );
				const comments = ts.getLeadingCommentRanges( text, node.getFullStart() ) || [];
				let translator = '';
				for ( const comment of comments ) {
					const raw = text.slice( comment.pos, comment.end );
					const match = raw.match( /translators:\s*([\s\S]*?)\s*\*\// );
					if ( match ) {
						translator = match[ 1 ].replace( /\s+/g, ' ' ).trim();
					}
				}
				const key = `${ name }\0${ msgid }\0${ name === '_x' || name === '_nx' ? stringArg( node.arguments[ 1 ] ) || '' : '' }`;
				const entry = messages.get( key ) || {
					name,
					msgid,
					msgidPlural: name === '_n' || name === '_nx' ? stringArg( node.arguments[ 1 ] ) : '',
					msgctxt: name === '_x' || name === '_nx' ? stringArg( node.arguments[ 1 ] ) : '',
					translator,
					refs: [],
				};
				entry.refs.push( `${ rel }:${ line + 1 }` );
				if ( ! entry.translator && translator ) {
					entry.translator = translator;
				}
				messages.set( key, entry );
			}
		}
		ts.forEachChild( node, visit );
	}

	visit( source );
}

function escapePo( value ) {
	return value
		.replace( /\\/g, '\\\\' )
		.replace( /"/g, '\\"' )
		.replace( /\t/g, '\\t' )
		.replace( /\r/g, '\\r' )
		.replace( /\n/g, '\\n' );
}

const lines = [
	'# Copyright (C) 2026 RankMySEO',
	'# This file is distributed under the GPL-2.0-or-later.',
	'msgid ""',
	'msgstr ""',
	'"Project-Id-Version: RankMySEO 1.0.0\\n"',
	'"MIME-Version: 1.0\\n"',
	'"Content-Type: text/plain; charset=UTF-8\\n"',
	'"Content-Transfer-Encoding: 8bit\\n"',
	'"X-Domain: rankmyseo\\n"',
	'',
];

for ( const entry of messages.values() ) {
	if ( entry.translator ) {
		lines.push( `#. translators: ${ entry.translator }` );
	}
	for ( const ref of entry.refs ) {
		lines.push( `#: ${ ref }` );
	}
	if ( entry.msgctxt ) {
		lines.push( `msgctxt "${ escapePo( entry.msgctxt ) }"` );
	}
	lines.push( `msgid "${ escapePo( entry.msgid ) }"` );
	if ( entry.msgidPlural ) {
		lines.push( `msgid_plural "${ escapePo( entry.msgidPlural ) }"` );
		lines.push( 'msgstr[0] ""' );
		lines.push( 'msgstr[1] ""' );
	} else {
		lines.push( 'msgstr ""' );
	}
	lines.push( '' );
}

fs.writeFileSync( outFile, lines.join( '\n' ) );
process.stdout.write( `Extracted ${ messages.size } JavaScript strings\n` );
