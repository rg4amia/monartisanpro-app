<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\StoreCommunicationRequest;
use App\Services\UploadLimitService;
use Tests\TestCase;

/**
 * Plafond de téléversement réellement applicable.
 *
 * Valider un fichier à 10 Mo alors que PHP en refuse 8 ne protège de rien :
 * la requête est coupée avant d'atteindre Laravel, le corps arrive vide, et
 * l'administrateur reçoit une erreur sans rapport — « le titre est
 * obligatoire » sur un formulaire qu'il vient de remplir. Sur un hébergement
 * mutualisé, ces directives ne sont pas modifiables depuis le code : le
 * plafond doit s'y conformer, et l'annoncer.
 *
 * `upload_max_filesize` et `post_max_size` étant `PHP_INI_PERDIR`, `ini_set`
 * ne les change pas à l'exécution : on substitue donc la lecture des
 * directives plutôt que de les modifier.
 */
class UploadLimitServiceTest extends TestCase
{
    /** Service lisant les directives fournies plutôt que celles du serveur. */
    private function serviceWith(string $uploadMax, string $postMax): UploadLimitService
    {
        return new class($uploadMax, $postMax) extends UploadLimitService
        {
            public function __construct(
                private string $uploadMax,
                private string $postMax,
            ) {}

            protected function directiveRaw(string $directive): string
            {
                return $directive === 'upload_max_filesize'
                    ? $this->uploadMax
                    : $this->postMax;
            }
        };
    }

    public function test_the_php_ceiling_wins_when_it_is_lower_than_the_wish(): void
    {
        // On souhaitait 10 Mo, PHP n'en accepte que 2.
        $this->assertSame(2048, $this->serviceWith('2M', '8M')->maxKilobytes(10240));
    }

    public function test_the_smallest_of_the_two_directives_applies(): void
    {
        // `post_max_size` couvre tout le corps : plus bas, c'est lui qui coupe.
        $this->assertSame(4096, $this->serviceWith('16M', '4M')->maxKilobytes(10240));
    }

    public function test_the_wish_wins_when_php_is_generous(): void
    {
        $this->assertSame(10240, $this->serviceWith('128M', '128M')->maxKilobytes(10240));
    }

    public function test_an_unlimited_directive_does_not_constrain(): void
    {
        // `post_max_size = 0` signifie « sans limite » : le lire comme un
        // plafond de zéro octet interdirait tout téléversement.
        $this->assertSame(10240, $this->serviceWith('32M', '0')->maxKilobytes(10240));
    }

    public function test_a_missing_directive_does_not_constrain(): void
    {
        $this->assertSame(10240, $this->serviceWith('', '')->maxKilobytes(10240));
    }

    public function test_the_ceiling_never_falls_to_zero(): void
    {
        // Une configuration absurde ne doit pas produire une règle `max:0`,
        // qui refuserait silencieusement tout fichier.
        $this->assertSame(1, $this->serviceWith('1', '1')->maxKilobytes(10240));
    }

    public function test_it_understands_the_ini_units(): void
    {
        // Le résultat est en octets, pas en kilooctets.
        $this->assertSame(512 * 1024, UploadLimitService::parseSize('512K'));
        $this->assertSame(8 * 1024 * 1024, UploadLimitService::parseSize('8M'));
        $this->assertSame(1024 * 1024 * 1024, UploadLimitService::parseSize('1G'));
        $this->assertSame(1048576, UploadLimitService::parseSize('1048576'));
        $this->assertNull(UploadLimitService::parseSize(''));
        $this->assertNull(UploadLimitService::parseSize(null));
    }

    public function test_the_announced_limit_reads_naturally(): void
    {
        $this->assertSame('8 Mo', $this->serviceWith('8M', '8M')->humanLimit(10240));
        $this->assertSame('700 Ko', $this->serviceWith('700K', '8M')->humanLimit(10240));
        $this->assertSame('1,5 Mo', $this->serviceWith('1536K', '8M')->humanLimit(10240));
    }

    public function test_the_communication_rule_uses_the_effective_ceiling(): void
    {
        // Le service réel : on vérifie le câblage, pas une valeur en dur, la
        // configuration de la machine de test n'étant pas celle du serveur.
        $expected = app(UploadLimitService::class)->maxKilobytes(
            StoreCommunicationRequest::AUDIO_MAX_KB,
        );

        $rules = (new StoreCommunicationRequest)->rules();

        $this->assertContains('max:'.$expected, $rules['media_file']);
        $this->assertLessThanOrEqual(
            StoreCommunicationRequest::AUDIO_MAX_KB,
            $expected,
        );
    }
}
