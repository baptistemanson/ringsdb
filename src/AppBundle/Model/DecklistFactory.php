<?php

namespace AppBundle\Model;

use AppBundle\Entity\Decklist;
use AppBundle\Entity\Deck;
use Doctrine\ORM\EntityManager;
use AppBundle\Helper\DeckValidationHelper;
use AppBundle\Services\Texts;
use AppBundle\Entity\Decklistslot;
use AppBundle\Entity\Decklistsideslot;

class DecklistFactory {
    public function __construct(EntityManager $doctrine, DeckValidationHelper $deckValidationHelper, Texts $texts) {
        $this->doctrine = $doctrine;
        $this->deckValidationHelper = $deckValidationHelper;
        $this->texts = $texts;
    }

    public function createDecklistFromDeck(Deck $deck, $name = null, $descriptionMd = null) {
        /* @var $lastPack \AppBundle\Entity\Pack */
        $lastPack = $deck->getLastPack();
        $problem = $this->deckValidationHelper->findProblem($deck, true);
        if ($problem) {
            throw new \Exception('This deck cannot be published  because it is invalid: "' . $this->deckValidationHelper->getProblemLabel($problem) . '".');
        }

        // all good for decklist publication

        // increasing deck version
        $deck->setMinorVersion(0);
        $deck->setMajorVersion($deck->getMajorVersion() + 1);

        if (empty($name)) {
            $name = $deck->getName();

            if (empty($name)) {
                $name = 'Untitled Deck';
            }
        }
        $name = substr($name, 0, 60);

        if (empty($descriptionMd)) {
            $descriptionMd = $deck->getDescriptionMd();
        }
        $description = $this->texts->markdown($descriptionMd);

        $countBySphere = $deck->getSlots()->getCountBySphere();
        $predominantSphere = array_keys($countBySphere, max($countBySphere))[0];
        $predominantSphere = $this->doctrine->getRepository('AppBundle:Sphere')->findOneBy(["code" => $predominantSphere]);

        $heroes = $deck->getSlots()->getHeroDeck();

        $content = [
            'main' => $deck->getSlots()->getContent(),
            'side' => $deck->getSideslots()->getContent(),
        ];
        $new_content = json_encode($content);
        $new_signature = md5($new_content);

        $decklist = new Decklist();
        $decklist->setName($name);
        $decklist->setVersion($deck->getVersion());
        $decklist->setNameCanonical($this->texts->slugify($name) . '-' . $decklist->getVersion());
        $decklist->setDescriptionMd($descriptionMd);
        $decklist->setDescriptionHtml($description);
        $decklist->setDateCreation(new \DateTime());
        $decklist->setDateUpdate(new \DateTime());
        $decklist->setSignature($new_signature);
        $decklist->setLastPack($deck->getLastPack());
        $decklist->setNbVotes(0);
        $decklist->setNbfavorites(0);
        $decklist->setNbcomments(0);
        $decklist->setUser($deck->getUser());

        foreach ($deck->getSlots() as $slot) {
            $decklistslot = new Decklistslot();
            $decklistslot->setQuantity($slot->getQuantity());
            $decklistslot->setCard($slot->getCard());
            $decklistslot->setDecklist($decklist);
            $decklist->getSlots()->add($decklistslot);
        }

        foreach ($deck->getSideslots() as $slot) {
            $decklistslot = new Decklistsideslot();
            $decklistslot->setQuantity($slot->getQuantity());
            $decklistslot->setCard($slot->getCard());
            $decklistslot->setDecklist($decklist);
            $decklist->getSideslots()->add($decklistslot);
        }

        $decklist->setPredominantSphere($predominantSphere);
        $decklist->setStartingThreat($decklist->getSlots()->getStartingThreat());

        foreach ($heroes as $hero) {
            $decklist->addSphere($hero->getCard()->getSphere());
        }

        if (count($deck->getChildren())) {
            $decklist->setPrecedent($deck->getChildren()[0]);
        } else if ($deck->getParent()) {
            $decklist->setPrecedent($deck->getParent());
        }
        $decklist->setParent($deck);

        $deck->setMinorVersion(1);

        return $decklist;
    }
}
