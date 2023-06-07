<?php

namespace WPCOMVIP\Governance\Tests;

use WPCOMVIP\Governance\RulesValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers RulesValidator
 */
class RulesValidatorTest extends TestCase {
	public function test_validate_schema__with_default_allowed_blocks_rule__passes_validation() {
		$rules = [
			'type'          => 'default',
			'allowedBlocks' => [
				'core/paragraph',
				'core/heading',
				'core/media-text',
			],
		];

		$this->assertTrue( RulesValidator::validate( $rules ) );
	}
}
