<?php
/**
 * Helpers for grouping lesson_profile posts by book title.
 *
 * @package My_Lesson_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derive book title from lesson post meta or post title.
 *
 * @param WP_Post $post Lesson profile post.
 * @return string
 */
function mle_get_lesson_book_title( $post ) {
	$meta = get_post_meta( $post->ID, '_mle_book_title', true );
	if ( is_string( $meta ) && $meta !== '' ) {
		return $meta;
	}

	$source = get_post_meta( $post->ID, '_mle_source_book_name', true );
	if ( is_string( $source ) && $source !== '' ) {
		return $source;
	}

	$title = $post->post_title;
	if ( preg_match( '/^(.+?)\s*-\s*(Chapter|Mid-Term|Midterm|Final)/i', $title, $matches ) ) {
		$book = trim( $matches[1] );
		// Strip leftover test prefixes like "TEST BATCH".
		$book = preg_replace( '/^(TEST\s+BATCH\s*[-:]?\s*)/i', '', $book );
		$book = trim( (string) $book );
		if ( $book !== '' ) {
			return $book;
		}
	}

	// Legacy titles such as "LESSON PLAN CHAPTER 1" have no book name.
	return __( 'Uncategorized', 'my-lesson-editor' );
}

/**
 * Classify a lesson title into chapter / midterm / final / other.
 *
 * @param string $title Post title.
 * @return string
 */
function mle_get_lesson_item_kind( $title ) {
	$upper = strtoupper( $title );
	if ( strpos( $upper, 'MID-TERM' ) !== false || strpos( $upper, 'MIDTERM' ) !== false ) {
		return 'midterm';
	}
	if ( strpos( $upper, 'FINAL' ) !== false ) {
		return 'final';
	}
	if ( preg_match( '/chapter\s*(\d+)/i', $title ) ) {
		return 'chapter';
	}
	return 'other';
}

/**
 * Sort key for natural chapter ordering within a book.
 *
 * @param WP_Post $post Lesson profile post.
 * @return array<int|string>
 */
function mle_get_lesson_sort_key( $post ) {
	$kind = mle_get_lesson_item_kind( $post->post_title );
	if ( 'chapter' === $kind && preg_match( '/chapter\s*(\d+)/i', $post->post_title, $matches ) ) {
		return array( 0, (int) $matches[1], $post->post_title );
	}
	if ( 'midterm' === $kind ) {
		return array( 1, 0, $post->post_title );
	}
	if ( 'final' === $kind ) {
		return array( 2, 0, $post->post_title );
	}
	return array( 3, 0, $post->post_title );
}

/**
 * Human label for a lesson row in the tree.
 *
 * @param WP_Post $post Lesson profile post.
 * @return string
 */
function mle_get_lesson_display_label( $post ) {
	$title = $post->post_title;
	if ( preg_match( '/-\s*(Chapter\s*\d+.*)$/i', $title, $matches ) ) {
		return trim( $matches[1] );
	}
	if ( preg_match( '/-\s*(Mid-Term.*|Final.*)$/i', $title, $matches ) ) {
		return trim( $matches[1] );
	}
	return $title;
}

/**
 * Group name inside a book (Lessons, Mid-Term, Final Exam, Other).
 *
 * @param string $kind From mle_get_lesson_item_kind().
 * @return string
 */
function mle_get_lesson_group_label( $kind ) {
	switch ( $kind ) {
		case 'chapter':
			return __( 'Lessons', 'my-lesson-editor' );
		case 'midterm':
			return __( 'Mid-Term', 'my-lesson-editor' );
		case 'final':
			return __( 'Final Exam', 'my-lesson-editor' );
		default:
			return __( 'Other', 'my-lesson-editor' );
	}
}

/**
 * Build grouped tree payload for the lesson editor right column.
 *
 * @param WP_Post[] $posts Lesson profile posts.
 * @return array<string,mixed>
 */
function mle_build_grouped_lesson_tree( array $posts ) {
	$books = array();

	foreach ( $posts as $post ) {
		$book_title = mle_get_lesson_book_title( $post );
		$kind       = mle_get_lesson_item_kind( $post->post_title );
		$group      = mle_get_lesson_group_label( $kind );

		if ( ! isset( $books[ $book_title ] ) ) {
			$books[ $book_title ] = array();
		}
		if ( ! isset( $books[ $book_title ][ $group ] ) ) {
			$books[ $book_title ][ $group ] = array();
		}

		$books[ $book_title ][ $group ][] = array(
			'id'           => (int) $post->ID,
			'title'        => $post->post_title,
			'label'        => mle_get_lesson_display_label( $post ),
			'status'       => $post->post_status,
			'kind'         => $kind,
			'lms_id'       => (int) get_post_meta( $post->ID, '_mle_academy_lesson_id', true ),
			'sort_key'     => mle_get_lesson_sort_key( $post ),
		);
	}

	$group_order = array(
		__( 'Lessons', 'my-lesson-editor' )     => 0,
		__( 'Mid-Term', 'my-lesson-editor' )    => 1,
		__( 'Final Exam', 'my-lesson-editor' )  => 2,
		__( 'Other', 'my-lesson-editor' )       => 3,
	);

	$output = array();
	foreach ( $books as $book_title => $groups ) {
		$group_list = array();
		foreach ( $groups as $group_label => $items ) {
			usort(
				$items,
				function ( $a, $b ) {
					return $a['sort_key'] <=> $b['sort_key'];
				}
			);
			foreach ( $items as &$item ) {
				unset( $item['sort_key'] );
			}
			unset( $item );
			$group_list[] = array(
				'label' => $group_label,
				'items' => $items,
				'order' => isset( $group_order[ $group_label ] ) ? $group_order[ $group_label ] : 99,
			);
		}
		usort(
			$group_list,
			function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);
		foreach ( $group_list as &$group_row ) {
			unset( $group_row['order'] );
		}
		unset( $group_row );

		$output[] = array(
			'title'  => $book_title,
			'groups' => $group_list,
		);
	}

	usort(
		$output,
		function ( $a, $b ) {
			return strcasecmp( $a['title'], $b['title'] );
		}
	);

	return array( 'books' => $output );
}
