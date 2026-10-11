<?php
/**
 * AcademyLM client (local CPT + optional external REST).
 *
 * Modes:
 *  - LOCAL  : if MLE_ACADEMYLM_BASE_URL is empty, courses are read from the
 *             local "academy_courses" post type and lessons are inserted as
 *             "academy_lesson" posts (same approach used by Academy LMS).
 *  - REMOTE : if MLE_ACADEMYLM_BASE_URL is set, courses are fetched and
 *             lessons are POSTed to that base. Endpoints can be filtered via
 *             "mle_academylm_endpoint".
 *
 * Constants (override in wp-config.php):
 *  - MLE_ACADEMYLM_BASE_URL    Base URL such as "https://lms.example.com".
 *  - MLE_ACADEMYLM_API_TOKEN   Optional bearer token.
 *
 * @package My_Lesson_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MLE_ACADEMYLM_BASE_URL' ) ) {
	define( 'MLE_ACADEMYLM_BASE_URL', '' );
}
if ( ! defined( 'MLE_ACADEMYLM_API_TOKEN' ) ) {
	define( 'MLE_ACADEMYLM_API_TOKEN', '' );
}

class MLE_AcademyLM_Client {

	const COURSE_CPT = 'academy_courses';
	const LESSON_CPT = 'academy_lesson';

	/**
	 * Whether the integration is using a remote REST API.
	 *
	 * @return bool
	 */
	public function is_remote() {
		return ! empty( MLE_ACADEMYLM_BASE_URL );
	}

	/**
	 * Build the REST endpoint for a logical action.
	 * Filterable via "mle_academylm_endpoint".
	 *
	 * @param string $action  One of "courses", "create_lesson".
	 * @param array  $context Extra context (e.g. course_id).
	 * @return string Full URL.
	 */
	protected function endpoint( $action, $context = array() ) {
		$base = trailingslashit( (string) MLE_ACADEMYLM_BASE_URL );
		$map  = array(
			'courses'        => $base . 'wp-json/academy-lms/v1/courses',
			'create_lesson'  => $base . 'wp-json/academy-lms/v1/lessons',
		);
		$url  = isset( $map[ $action ] ) ? $map[ $action ] : '';
		return apply_filters( 'mle_academylm_endpoint', $url, $action, $context );
	}

	/**
	 * Default headers for remote calls.
	 *
	 * @return array
	 */
	protected function headers() {
		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
		if ( ! empty( MLE_ACADEMYLM_API_TOKEN ) ) {
			$headers['Authorization'] = 'Bearer ' . MLE_ACADEMYLM_API_TOKEN;
		}
		return apply_filters( 'mle_academylm_headers', $headers );
	}

	/**
	 * Get a list of courses ready for a <select>.
	 *
	 * @return array<int,array{id:int|string,title:string}>
	 */
	public function get_courses() {
		if ( $this->is_remote() ) {
			$response = wp_remote_get(
				$this->endpoint( 'courses' ),
				array(
					'timeout' => 20,
					'headers' => $this->headers(),
				)
			);
			if ( is_wp_error( $response ) ) {
				return array();
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				return array();
			}
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) ) {
				return array();
			}
			$rows = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;
			$out  = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id    = isset( $row['id'] ) ? $row['id'] : ( isset( $row['ID'] ) ? $row['ID'] : '' );
				$title = isset( $row['title'] ) ? $row['title'] : ( isset( $row['name'] ) ? $row['name'] : '' );
				if ( $id === '' || $title === '' ) {
					continue;
				}
				$out[] = array(
					'id'    => is_numeric( $id ) ? (int) $id : sanitize_text_field( (string) $id ),
					'title' => wp_strip_all_tags( (string) $title ),
				);
			}
			return $out;
		}

		// LOCAL: read from CPT "academy_courses" if it exists.
		if ( ! post_type_exists( self::COURSE_CPT ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => self::COURSE_CPT,
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$out[] = array(
				'id'    => (int) $p->ID,
				'title' => $p->post_title,
			);
		}
		return $out;
	}

	/**
	 * Create a lesson against AcademyLM.
	 *
	 * @param array $payload {
	 *     @type string $title     Lesson title.
	 *     @type string $content   HTML content.
	 *     @type int|string $course_id Course identifier.
	 * }
	 * @return array|WP_Error On success, an array with at least "lesson_id".
	 */
	public function create_lesson( array $payload ) {
		$title     = isset( $payload['title'] ) ? wp_strip_all_tags( (string) $payload['title'] ) : '';
		$content   = isset( $payload['content'] ) ? (string) $payload['content'] : '';
		$course_id = isset( $payload['course_id'] ) ? $payload['course_id'] : '';

		if ( $title === '' || $content === '' ) {
			return new WP_Error( 'mle_lms_invalid', __( 'Title and content are required.', 'my-lesson-editor' ) );
		}

		if ( $this->is_remote() ) {
			$body = wp_json_encode(
				array(
					'course_id' => $course_id,
					'title'     => $title,
					'content'   => $content,
				)
			);
			$response = wp_remote_post(
				$this->endpoint( 'create_lesson', array( 'course_id' => $course_id ) ),
				array(
					'timeout' => 30,
					'headers' => $this->headers(),
					'body'    => $body,
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			$raw  = wp_remote_retrieve_body( $response );
			if ( $code < 200 || $code >= 300 ) {
				return new WP_Error( 'mle_lms_http', sprintf( 'HTTP %d: %s', $code, substr( $raw, 0, 300 ) ) );
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				return new WP_Error( 'mle_lms_decode', __( 'Invalid JSON response from AcademyLM.', 'my-lesson-editor' ) );
			}
			$lesson_id = isset( $decoded['lesson_id'] )
				? $decoded['lesson_id']
				: ( isset( $decoded['id'] ) ? $decoded['id'] : 0 );
			return array(
				'lesson_id' => $lesson_id,
				'remote'    => true,
				'response'  => $decoded,
			);
		}

		// LOCAL: use Academy LMS plugin tables when available.
		if ( class_exists( '\Academy\Classes\Query' ) && class_exists( '\Academy\Helper' ) ) {
			return $this->create_local_academy_lesson( $title, $content, $course_id, $payload );
		}

		// Legacy fallback CPT (rarely used).
		if ( ! post_type_exists( self::LESSON_CPT ) ) {
			return new WP_Error( 'mle_lms_missing', __( 'Academy LMS is not active. Activate the Academy plugin or define MLE_ACADEMYLM_BASE_URL.', 'my-lesson-editor' ) );
		}
		$lesson_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => wp_kses_post( $content ),
				'post_status'  => 'publish',
				'post_type'    => self::LESSON_CPT,
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $lesson_id ) ) {
			return $lesson_id;
		}
		if ( $course_id ) {
			update_post_meta( $lesson_id, '_academy_course_id', is_numeric( $course_id ) ? (int) $course_id : sanitize_text_field( (string) $course_id ) );
		}
		return array(
			'lesson_id' => (int) $lesson_id,
			'remote'    => false,
		);
	}

	/**
	 * Create a lesson via Academy LMS custom tables and attach to course curriculum.
	 *
	 * @param string              $title     Lesson title.
	 * @param string              $content   HTML content.
	 * @param int|string          $course_id Course post ID.
	 * @param array<string,mixed> $payload   Optional extra fields (kind, lesson_profile_id).
	 * @return array|WP_Error
	 */
	protected function create_local_academy_lesson( $title, $content, $course_id, array $payload = array() ) {
		$lesson_profile_id = isset( $payload['lesson_profile_id'] ) ? (int) $payload['lesson_profile_id'] : 0;
		$kind              = isset( $payload['kind'] ) ? sanitize_text_field( (string) $payload['kind'] ) : mle_get_lesson_item_kind( $title );

		if ( $lesson_profile_id > 0 ) {
			$existing = (int) get_post_meta( $lesson_profile_id, '_mle_academy_lesson_id', true );
			if ( $existing > 0 ) {
				// Update LMS lesson content/title when republishing an existing mapping.
				\Academy\Classes\Query::lesson_insert(
					array(
						'ID'             => $existing,
						'lesson_title'   => $title,
						'lesson_content' => wp_kses_post( $content ),
						'lesson_status'  => 'publish',
					)
				);
				$this->append_lesson_to_course_curriculum( (int) $course_id, $existing, $title, $kind );
				return array(
					'lesson_id' => $existing,
					'remote'    => false,
					'existing'  => true,
					'updated'   => true,
				);
			}
		}

		$lesson_id = \Academy\Classes\Query::lesson_insert(
			array(
				'lesson_author'  => get_current_user_id(),
				'lesson_title'   => $title,
				'lesson_name'    => \Academy\Helper::generate_unique_lesson_slug( $title ),
				'lesson_content' => wp_kses_post( $content ),
				'lesson_status'  => 'publish',
			)
		);

		if ( ! $lesson_id ) {
			return new WP_Error( 'mle_lms_insert', __( 'Failed to create Academy LMS lesson.', 'my-lesson-editor' ) );
		}

		\Academy\Classes\Query::lesson_meta_insert(
			$lesson_id,
			array(
				'featured_media' => 0,
				'attachment'     => 0,
				'is_previewable' => false,
				'video_duration' => array(
					'hours'   => 0,
					'minutes' => 0,
					'seconds' => 0,
				),
				'video_source'   => wp_json_encode(
					array(
						'type' => 'html5',
						'url'  => '',
					)
				),
			)
		);

		if ( $course_id ) {
			$this->append_lesson_to_course_curriculum( (int) $course_id, (int) $lesson_id, $title, $kind );
		}

		if ( $lesson_profile_id > 0 ) {
			update_post_meta( $lesson_profile_id, '_mle_academy_lesson_id', (int) $lesson_id );
			if ( $course_id ) {
				update_post_meta( $lesson_profile_id, '_mle_academy_course_id', (int) $course_id );
			}
		}

		return array(
			'lesson_id' => (int) $lesson_id,
			'remote'    => false,
			'existing'  => false,
		);
	}

	/**
	 * Append a lesson topic to an Academy course curriculum meta.
	 *
	 * @param int    $course_id   academy_courses post ID.
	 * @param int    $lesson_id   academy_lessons table row ID.
	 * @param string $lesson_title Topic label.
	 * @param string $kind        chapter|midterm|final|other.
	 * @return void
	 */
	public function append_lesson_to_course_curriculum( $course_id, $lesson_id, $lesson_title, $kind = 'chapter' ) {
		if ( $course_id <= 0 || $lesson_id <= 0 ) {
			return;
		}

		$curriculum = get_post_meta( $course_id, 'academy_course_curriculum', true );
		if ( ! is_array( $curriculum ) ) {
			$curriculum = array();
		}

		$section_title = __( 'Lessons', 'my-lesson-editor' );
		if ( 'midterm' === $kind ) {
			$section_title = __( 'Mid-Term', 'my-lesson-editor' );
		} elseif ( 'final' === $kind ) {
			$section_title = __( 'Final Exam', 'my-lesson-editor' );
		} elseif ( 'other' === $kind ) {
			$section_title = __( 'Other', 'my-lesson-editor' );
		}

		$section_index = null;
		foreach ( $curriculum as $index => $section ) {
			if ( isset( $section['title'] ) && $section['title'] === $section_title ) {
				$section_index = $index;
				break;
			}
		}

		if ( null === $section_index ) {
			$curriculum[] = array(
				'title'   => $section_title,
				'content' => '',
				'topics'  => array(),
			);
			$section_index = count( $curriculum ) - 1;
		}

		if ( ! isset( $curriculum[ $section_index ]['topics'] ) || ! is_array( $curriculum[ $section_index ]['topics'] ) ) {
			$curriculum[ $section_index ]['topics'] = array();
		}

		$already = false;
		foreach ( $curriculum[ $section_index ]['topics'] as $topic ) {
			if ( isset( $topic['id'], $topic['type'] ) && (int) $topic['id'] === (int) $lesson_id && 'lesson' === $topic['type'] ) {
				$already = true;
				break;
			}
		}

		if ( ! $already ) {
			$curriculum[ $section_index ]['topics'][] = array(
				'id'   => (int) $lesson_id,
				'name' => $lesson_title,
				'type' => 'lesson',
			);
		}

		// Keep chapter order correct across batches (not append/batch order).
		$curriculum[ $section_index ]['topics'] = $this->sort_curriculum_topics(
			$curriculum[ $section_index ]['topics']
		);

		update_post_meta( $course_id, 'academy_course_curriculum', $curriculum );
	}

	/**
	 * Sort curriculum topics by chapter number, then midterm/final, then title.
	 *
	 * @param array<int,array<string,mixed>> $topics Curriculum topics.
	 * @return array<int,array<string,mixed>>
	 */
	protected function sort_curriculum_topics( array $topics ) {
		usort(
			$topics,
			function ( $a, $b ) {
				$ka = $this->curriculum_topic_sort_key( isset( $a['name'] ) ? (string) $a['name'] : '' );
				$kb = $this->curriculum_topic_sort_key( isset( $b['name'] ) ? (string) $b['name'] : '' );
				return $ka <=> $kb;
			}
		);
		return array_values( $topics );
	}

	/**
	 * Build a comparable sort key for a curriculum topic title.
	 *
	 * @param string $title Topic / lesson title.
	 * @return array{0:int,1:int,2:string}
	 */
	protected function curriculum_topic_sort_key( $title ) {
		$kind = function_exists( 'mle_get_lesson_item_kind' ) ? mle_get_lesson_item_kind( $title ) : 'other';
		if ( 'chapter' === $kind && preg_match( '/chapter\s*(\d+)/i', $title, $matches ) ) {
			return array( 0, (int) $matches[1], strtolower( $title ) );
		}
		if ( 'midterm' === $kind ) {
			return array( 1, 0, strtolower( $title ) );
		}
		if ( 'final' === $kind ) {
			return array( 2, 0, strtolower( $title ) );
		}
		return array( 3, 0, strtolower( $title ) );
	}

	/**
	 * Extract plain text from an uploaded file (TXT/DOCX/PDF/DOC, best effort).
	 *
	 * @param string $tmp_path  Server-side temp path.
	 * @param string $filename  Original file name (used for extension hint).
	 * @return string|WP_Error Extracted text on success.
	 */
	public function extract_text( $tmp_path, $filename ) {
		if ( ! is_string( $tmp_path ) || ! file_exists( $tmp_path ) || ! is_readable( $tmp_path ) ) {
			return new WP_Error( 'mle_extract_unreadable', __( 'Uploaded file is not readable.', 'my-lesson-editor' ) );
		}
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( $ext === 'txt' || $ext === 'md' || $ext === 'csv' ) {
			$raw = file_get_contents( $tmp_path );
			if ( $raw === false ) {
				return new WP_Error( 'mle_extract_read', __( 'Could not read text file.', 'my-lesson-editor' ) );
			}
			return $this->normalize_text( $raw );
		}

		if ( $ext === 'docx' ) {
			$text = $this->extract_docx( $tmp_path );
			if ( is_wp_error( $text ) ) {
				return $text;
			}
			return $this->normalize_text( $text );
		}

		// PDF / DOC fallback: scan printable strings (best effort, no external lib).
		$raw = @file_get_contents( $tmp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( $raw === false ) {
			return new WP_Error( 'mle_extract_read', __( 'Could not read uploaded file.', 'my-lesson-editor' ) );
		}
		$cleaned = preg_replace( '/[^\P{C}\n\r\t\x20-\x7E\xA0-\x{10FFFF}]+/u', ' ', $raw );
		$cleaned = preg_replace( '/\s{2,}/u', ' ', (string) $cleaned );
		return trim( (string) $cleaned );
	}

	/**
	 * Best-effort extractor for DOCX files (zip with word/document.xml).
	 *
	 * @param string $path
	 * @return string|WP_Error
	 */
	protected function extract_docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'mle_extract_zip', __( 'ZipArchive extension is required to read DOCX.', 'my-lesson-editor' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'mle_extract_zip', __( 'Could not open DOCX archive.', 'my-lesson-editor' ) );
		}
		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();
		if ( ! is_string( $xml ) || $xml === '' ) {
			return new WP_Error( 'mle_extract_xml', __( 'DOCX is missing word/document.xml.', 'my-lesson-editor' ) );
		}
		// Convert paragraph and break tags to newlines, then strip.
		$text = preg_replace( '/<w:p[^>]*>/u', "\n", $xml );
		$text = preg_replace( '/<w:br\s*\/>/u', "\n", (string) $text );
		$text = wp_strip_all_tags( (string) $text );
		return html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Collapse whitespace and trim to a sane size.
	 *
	 * @param string $raw
	 * @return string
	 */
	protected function normalize_text( $raw ) {
		$text = (string) $raw;
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( "/\n{3,}/u", "\n\n", $text );
		$text = preg_replace( '/[ \t]{2,}/u', ' ', (string) $text );
		return trim( (string) $text );
	}
}
