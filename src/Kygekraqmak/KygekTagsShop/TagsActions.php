<?php

/*
 *  PLUGIN BY:
 *   _    __                  _                                     _
 *  | |  / /                 | |                                   | |
 *  | | / /                  | |                                   | |
 *  | |/ / _   _  ____   ____| | ______ ____   _____ ______   ____ | | __
 *  | |\ \| | | |/ __ \ / __ \ |/ /  __/ __ \ / __  | _  _ \ / __ \| |/ /
 *  | | \ \ \_| | <__> |  ___/   <| / | <__> | <__| | |\ |\ | <__> |   <
 *  |_|  \_\__  |\___  |\____|_|\_\_|  \____^_\___  |_||_||_|\____^_\|\_\
 *            | |    | |                          | |
 *         ___/ | ___/ |                          | |
 *        |____/ |____/                           |_|
 *
 * A PocketMine-MP plugin to buy tags with money
 * Copyright (C) 2020 Kygekraqmak
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace Kygekraqmak\KygekTagsShop;

use pocketmine\Player;

/**
 * Class TagsActions
 * KygekTagsShop API class
 *
 * @package Kygekraqmak\KygekTagsShop
 */
class TagsActions {

    public const API_VERSION = 1.0;

    /** @var array */
    private $tags = [];

    /** @var array */
    private $config;
    /** @var array */
    private $data;

    /** @var bool */
    private $economyEnabled;
    /** @var onebone\economyapi\EconomyAPI|null */
    private $economyAPI;

    public function __construct() {
        $this->config = TagsShop::getPlugin()->config;
        $this->data = TagsShop::getPlugin()->data;
        $this->economyEnabled = TagsShop::getPlugin()->economyEnabled;
        $this->economyAPI = TagsShop::getPlugin()->economyAPI;
    }

    /**
     * Get tags in config file
     * Returns an multidimensional associative array (ID => [tag => price])
     * ID always starts from 0 and is ordered as that of in config file
     *
     * @return array
     */
    public function getAllTags() : array {
        foreach ($this->config["tags"] as $tag) {
            $tag = explode(":", $tag);
            $this->tags[][$tag[0]] = $tag[1];
        }

        return $this->tags;
    }

    /**
     * Get price of a tag
     * Returns null if:
     * - EconomyAPI plugin is not installed or enabled, and/or
     * - tag ID doesn't exists
     *
     * @param int $tagid
     * @return null|int
     */
    public function getTagPrice(int $tagid) : ?int {
        if (!$this->economyEnabled or !$this->tagExists($tagid)) return null;

        return (int) array_values($this->getAllTags()[$tagid])[0];
    }

    /**
     * Get tag display
     * Returns null if tag ID doesn't exists
     *
     * @param int $tagid
     * @return null|string
     */
    public function getTagName(int $tagid) : ?string {
        if (!$this->tagExists($tagid)) return null;

        return array_keys($this->getAllTags()[$tagid])[0];
    }

    /**
     * Checks if tag exists in config
     *
     * @param int $tagid
     * @return bool
     */
    public function tagExists(int $tagid) : bool {
        return isset($this->getAllTags()[$tagid]);
    }

    /**
     * Checks if player has tag
     *
     * @param Player $player
     * @return bool
     */
    public function playerHasTag(Player $player) : bool {
        return isset($this->getAllData()[$player->getLowercaseName()]);
    }
    /**
     * Gets player's tag (ID) from config
     *
     * @param Player $player
     * @return null|int
     */
    public function getPlayerTag(Player $player) : ?int {
        if (!$this->playerHasTag($player)) return null;

        return $this->getAllData()[$player->getLowercaseName()];
    }

    /**
     * Unsets the player's tag
     *
     * @param Player $player
     */
    public function unsetPlayerTag(Player $player) {
        if (!$this->playerHasTag($player)) {
            $player->sendMessage(TagsShop::WARNING . "You cannot buy tags because you haven't owned a tag!");
            return;
        }

        $this->removeData($player);
        $player->setDisplayName($player->getName());

        if ($this->economyEnabled) {
            $this->economyAPI->addMoney($player, $this->getTagPrice($this->getPlayerTag($player)));
        }
    }

    /**
     * Sets a tag to player
     *
     * @param Player $player
     * @param int $tagid
     */
    public function setPlayerTag(Player $player, int $tagid) {

        if ($this->playerHasTag($player)) {
            $player->sendMessage(TagsShop::WARNING . "You cannot buy tags because you have owned a tag!");
            return;
        }

        $playermoney = $this->economyAPI->myMoney($player);
        $tagprice = $this->getTagPrice($tagid);
        $currency = $this->economyAPI->getMonetaryUnit();

        if ($this->economyEnabled) {
            if ($playermoney < $tagprice) {
                $player->sendMessage(TagsShop::WARNING . "You need " . $currency . $tagprice - $playermoney . "to buy this tag!");
                return;
            }

            $this->setData($player, $tagid);
            $player->setDisplayName($player->getDisplayName() . " " . $this->getTagName($tagid));
            $player->sendMessage(TagsShop::INFO . "Successfully set your tag to " . $this->getTagName($tagid) . " for " . $currency . $tagprice);
            return;
        }

        $this->setData($player, $tagid);
        $player->setDisplayName($player->getName() . " " . $this->getTagName($tagid));
        $player->sendMessage(TagsShop::INFO . "Successfully set your tag to " . $this->getTagName($tagid));
    }

    /**
     * Gets KygekTagsShop API version
     *
     * @return float
     */
    public function getAPIVersion() : float {
        return self::API_VERSION;
    }

    /**
     * Gets the tag ID of a player from KygekTagsShop database
     *
     * @param Player $player
     * @return int
     */
    public function getData(Player $player) : int {
        return $this->data->get($player->getLowercaseName());
    }

    /**
     * Sets tag ID to a player inside KygekTagsShop database
     *
     * @param Player $player
     * @param int $tagid
     */
    public function setData(Player $player, int $tagid) {
        $this->data->set($player->getLowercaseName(), $tagid);
        $this->saveData();
        $this->reloadData();
    }

    /**
     * Removes player tag ID from KygekTagsShop database
     *
     * @param Player $player
     */
    public function removeData(Player $player) {
        $this->data->remove($player->getLowercaseName());
        $this->saveData();
        $this->reloadData();
    }

    /**
     * Gets all KygekTagsShop database contents
     *
     * @param bool $keys
     * @return array
     */
    public function getAllData(bool $keys = false) : array {
        return $this->data->getAll($keys);
    }

    /**
     * Reloads the KygekTagsShop database
     */
    public function reloadData() {
        $this->data->reload();
    }

    /**
     * Saves the KygekTagsShop database
     */
    public function saveData() {
        $this->data->save();
    }

    /**
     * Gets the KygekTagsShop database location
     *
     * @return string
     */
    public function getDataLocation() : string {
        return TagsShop::getPlugin()->getDataFolder() . "data.yml";
    }

}
