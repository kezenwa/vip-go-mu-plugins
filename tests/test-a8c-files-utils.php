<?php

class VIP_Go_A8C_Files_Utils_Test extends WP_UnitTestCase {

	private $a8c_files;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../a8c-files.php' );
		require_once( __DIR__ . '/../files/class-api-client.php' );
	}

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		$this->api_client = null;
		parent::tearDown();
	}

	private function setup_a8c_files_and_mock_client( $method_name, $expected ) {
		$mock = $this->getMockBuilder( 'Automattic\VIP\Files\API_Client' )
		             ->setConstructorArgs( array( 'https://files.go-vip.co', 123456, 'super-sekret-token' ) )
		             ->getMock();
		$mock->method( $method_name )
		     ->willReturn( $expected );

		$this->a8c_files = new A8C_Files( $mock );

		return $mock;
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class  = new \ReflectionClass( __NAMESPACE__ . '\A8C_Files' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}

	public function get_data_for_filter_photon_domain() {
		return [
			'image_on_home_url' => [
				'http://example.com/image.jpg',
				'http://example.com',
			],

			'image_on_go-vip_url' => [
				'http://example.go-vip.co/image.jpg',
				'http://example.go-vip.co',
			],

			'image_on_external_url' => [
				'http://external-url.com/image.jpg',
				'http://i0.wp.com',
			],
		];
	}

	/**
	 * @dataProvider get_data_for_filter_photon_domain
	 */
	public function test__filter_photon_domain( $image_url, $expected_photon_url ) {
		add_filter( 'home_url', function() {
			return 'http://example.com';
		} );

		$actual_photon_url = A8C_Files_Utils::filter_photon_domain( 'http://i0.wp.com', $image_url );

		$this->assertEquals( $expected_photon_url, $actual_photon_url );
	}

	public function get_data_for_strip_dimensions_from_url_path() {
		return [
			'invalid-url' => [
				'invalid-url',
				'invalid-url',
			],

			'no-dimensions' => [
				'https://example.com/wp-content/uploads/image.jpg',
				'https://example.com/wp-content/uploads/image.jpg',
			],

			'dimensions-jpg' => [
				'https://example.com/wp-content/uploads/image-800x400.jpg',
				'https://example.com/wp-content/uploads/image.jpg',
			],

			'dimensions-png' => [
				'https://example.com/wp-content/uploads/image-800x400.png',
				'https://example.com/wp-content/uploads/image.png',
			],

			'dimensions-gif' => [
				'https://example.com/wp-content/uploads/image-800x400.gif',
				'https://example.com/wp-content/uploads/image.gif',
			],

			'dimensions-jpeg' => [
				'https://example.com/wp-content/uploads/image-800x400.jpeg',
				'https://example.com/wp-content/uploads/image.jpeg',
			],

			'dimensions-jpg-case-insensitive' => [
				'https://example.com/wp-content/uploads/Image-800x400.jPg',
				'https://example.com/wp-content/uploads/Image.jPg',
			],

			'double-dimensions' => [
				'https://example.com/wp-content/uploads/image-800x400-350x120.jpg',
				'https://example.com/wp-content/uploads/image-800x400.jpg',
			],

			'double-same-dimensions' => [
				'https://example.com/wp-content/uploads/image-400x400-400x400.jpg',
				'https://example.com/wp-content/uploads/image-400x400.jpg',
			],

			'dimensions-with-querystring' => [
				'https://example.com/wp-content/uploads/image-400x450.png?resize=338%2C600&strip=all',
				'https://example.com/wp-content/uploads/image.png?resize=338%2C600&strip=all',
			],
		];
	}

	/**
	 * @dataProvider get_data_for_strip_dimensions_from_url_path
	 */
	public function test__strip_dimensions_from_url_path( $source, $expected ) {
		$actual = A8C_Files_Utils::strip_dimensions_from_url_path( $source );

		$this->assertEquals( $expected, $actual );
	}

	public function get_data_for_test_attachment_file_exists() {
		return [
			'invalid-path'  => [
				'/wp-content/uploads/file-invalid-path.jpg',
				new WP_Error( 'invalid-path' ),
			],
			'valid-image'   => [
				'/wp-content/uploads/file-exists.jpg',
				true,
			],
			'invalid-image' => [
				'/wp-content/uploads/file-notexists.jpg',
				false,
			],
			'WP-Error'      => [
				'/wp-content/uploads/file-wperror.jpg',
				new WP_Error( 'is_file-failed' ),
			],
		];
	}

	/**
	 * @dataProvider get_data_for_test_attachment_file_exists
	 */
	public function test_attachment_file_exists( $source, $expected ) {
		$this->setup_a8c_files_and_mock_client( 'is_file', $expected );

		$actual = $this->a8c_files->attachment_file_exists( $source );
		$this->assertEquals( $expected, $actual );
	}


	public function get_data_for_test_upload_file() {
		return [
			'invalid-local-filepath'  => [
				[ 'file' => '/wp-content/file-not-exits.txt' ],
				'',
				'/wp-content/file-not-exits.txt',
				[
					'error' => 'The specified local upload file does not exist.',
					'file'  => '/wp-content/file-not-exits.txt'
				]
			],
			'invalid-upload-filepath' => [
				[
					'file' => '/tmp/file-valid.txt',
					'url'  => 'https://files.go-vip.co/wp-content/invalid-upload-filepath.txt'
				],
				'',
				new WP_Error( 'invalid file path', 'invalid file path' ),
				[
					'error' => 'invalid file path',
					'url'   => 'https://files.go-vip.co/wp-content/invalid-upload-filepath.txt',
					'file'  => '/tmp/file-valid.txt'
				]
			],
			'valid-local-filepath'    => [
				[
					'file' => '/tmp/file-valid.txt',
					'url'  => '/wp-content/file-valid.txt'
				],
				'',
				'/wp-content/file-valid.txt',
				[
					'file' => '/wp-content/file-valid.txt',
					'url'  => '/wp-content/file-valid.txt'
				],
			],

		];
	}

	/**
	 * @dataProvider get_data_for_test_upload_file
	 */
	public function test_attachment_upload_file( $details, $upload_type, $mock_result, $expected ) {
		$this->setup_a8c_files_and_mock_client( 'upload_file', $mock_result );

		//create a physical file that upload_file will validate against 
		$handle = fopen( '/tmp/file-valid.txt', 'w' );
		fwrite( $handle, 'testdata' );
		fclose( $handle );
		$actual = $this->a8c_files->upload_file( $details, $upload_type );
		unlink( '/tmp/file-valid.txt' );

		$this->assertEquals( $expected, $actual );
	}


	public function get_data_for_test_delete_file() {
		return [
			'valid-image'  => [
				'wp-content/uploads/file-exists.jpg',
				'https://files.vipv2.net/wp-content/uploads/wp-content/uploads/file-exists.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_data_for_test_delete_file
	 */
	public function test_delete_file( $file_path, $expected ) {
		$mock = $this->setup_a8c_files_and_mock_client( 'delete_file', null );

		$mock->expects( $this->once() )
		     ->method( 'delete_file' )
		     ->with( $expected );

		$this->a8c_files->delete_file( $file_path );
	}

	public function test_delete_file_wp_error() {
		$api_client_mock = $this->setup_a8c_files_and_mock_client( 'delete_file', new WP_Error( 'invalid-path', 'invalid-path' ) );

		$mock_files = $this->getMockBuilder( 'A8C_Files' )
		                   ->setMethods( array( 'process_error' ) )
		                   ->setConstructorArgs( array( $api_client_mock ) )
		                   ->getMock();

		$mock_files->expects( $this->once() )
		           ->method( 'process_error' )
		           ->with( 'invalid-path' );

		$mock_files->delete_file( 'path' );
	}

	public function get_data_for_test_delete_file_purge_cache() {

		return [
			'valid-image' => [
				'file-exists.jpg',
				'/wp-content/uploads/file-exists.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_data_for_test_delete_file_purge_cache
	 */
	public function test_delete_file_purge_cache( $file_path, $expected ) {
		$api_client_mock = $this->setup_a8c_files_and_mock_client( 'delete_file', null );

		$mock = $this->getMockBuilder( 'A8C_Files' )
		             ->setMethods( array( 'purge_file_cache' ) )
		             ->setConstructorArgs( array( $api_client_mock ) )
		             ->getMock();

		$mock->expects( $this->once() )
		     ->method( 'purge_file_cache' )
		     ->with( get_site_url() . $expected, 'PURGE' );

		$mock->delete_file( $file_path );
	}

	public function get_data_for_test_check_uniqueness_with_backend() {
		return [
			'unique-filename' => [
				'filename-valid.txt',
				[ 'filename-valid.txt-unique' ],
			],
			'server-error'    => [
				'server-error.txt',
				[ new  WP_Error('server-error.txt') ],
			],
		];
	}

	/**
	 * @dataProvider get_data_for_test_check_uniqueness_with_backend
	 */
	public function test_attachment_filter__check_uniqueness_with_backend( $file_path, $expected ) {
		$this->setup_a8c_files_and_mock_client( 'unique_filename', $expected );

		$call_api_method = self::get_method( '_check_uniqueness_with_backend' );
		$actual = $call_api_method->invokeArgs( $this->a8c_files, [ $file_path ] );

		$this->assertEquals( $expected, $actual);
	}


}

