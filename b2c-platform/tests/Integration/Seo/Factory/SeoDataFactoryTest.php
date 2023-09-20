<?php

declare(strict_types=1);

namespace Tests\Integration\Seo\Factory;

use App\Clients\Resources\Company;
use App\Seo\Factory\SeoDataFactory;
use Tests\Integration\Concerns\InteractsWithBackOfficeApiClient;
use Tests\Integration\IntegrationTestCase;

class SeoDataFactoryTest extends IntegrationTestCase
{
    use InteractsWithBackOfficeApiClient;

    /** @var SeoDataFactory */
    private $SUT;

    protected function setUp(): void
    {
        parent::setUp();

        $this->SUT = $this->app->make(SeoDataFactory::class);
    }

    public function testGetSeoTitleAndDescriptionForCompanyPage(): void
    {
        $seoData = $this->SUT->makeCompanyPageSeoData(
            $this->givenCompany(
                $this->givenPresentation()
            )
        );

        $this->assertSame('Allfree B.V. - Jouw vakspecialist!', $seoData->getTitle());
        $this->assertSame(
            'Dé specialist voor het echte werk!' .
            ' ✓ Persoonlijk contact' .
            ' ✓ Met zorg geholpen',
            $seoData->getDescription()
        );

        $seoData = $this->SUT->makeCompanyPageSeoData(
            $this->givenCompany(
                $this->givenPresentation(['Snelle service', null])
            )
        );

        $this->assertSame('Allfree B.V. - Jouw vakspecialist!', $seoData->getTitle());
        $this->assertSame(
            'Dé specialist voor het echte werk!' .
            ' ✓ Snelle service' .
            ' ✓ Persoonlijk contact' .
            ' ✓ Met zorg geholpen',
            $seoData->getDescription()
        );

        $seoData = $this->SUT->makeCompanyPageSeoData(
            $this->givenCompany(
                $this->givenPresentation(['Snelle service', 'Reageert binnen 2 uur!']),
                [
                    new Company\Profession(1, 'Loodgieter'),
                ]
            )
        );

        $this->assertSame('Allfree B.V. - Jouw loodgieter!', $seoData->getTitle());
        $this->assertSame(
            'Dé specialist voor het echte werk!' .
            ' ✓ Snelle service' .
            ' ✓ Persoonlijk contact' .
            ' ✓ Met zorg geholpen',
            $seoData->getDescription()
        );
    }

    public function testMakeProfessionCityPageSeoData(): void
    {
        $seoData = $this->SUT->makeProfessionCityPageSeoData(
            $this->givenProfession(),
            $this->givenCity()
        );

        $this->assertSame('Betrouwbare loodgieter in Groningen nodig? - Direct geholpen!', $seoData->getTitle());
        $this->assertSame(
            'Direct een goede loodgieter in Groningen nodig?' .
            ' ✓ Snel geholpen' .
            ' ✓ Betrouwbare loodgieters' .
            ' ✓ Met zorg geselecteerd' .
            ' ✓ Gratis offerte ✓ Direct bellen',
            $seoData->getDescription()
        );
    }
}
