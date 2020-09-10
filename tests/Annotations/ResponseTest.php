<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Annotations;

use OpenApi\Tests\OpenApiTestCase;

class ResponseTest extends OpenApiTestCase
{
    public function responses()
    {
        return [
            'misspelled-default' => ['Default'],
            'misspelled-range-definition' => ['5xX'],
            'wrong-range-definition' => ['6XX'],
        ];
    }

    /**
     * @dataProvider responses
     */
    public function testMisspelledResponse(string $response = '')
    {
        $annotations = $this->parseComment(
            '@OA\Get(@OA\Response(response="'.$response.'", description="description"))'
        );
        /*
         * @see Annotations/Operation.php:187
         */
        $this->assertOpenApiLogEntryContains(
            'Invalid value "'.$response.'" for @OA\Response(response="'.$response.'")->response, expecting "default", a HTTP Status Code or HTTP Status Code range definition'
        );
        $annotations[0]->validate();
    }
}
