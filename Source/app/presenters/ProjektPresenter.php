<?php

namespace App\Presenters;

use Nette\Database\Context;
use Nette\Application\UI\Form;

use Nette,
	App\Model;
/**
 * Projekt presenter.
 */

class ProjektPresenter extends BasePresenter
{
	/** @persistent */
	public $id;

        public function handleGetIn($projectID)
        {      
            $user = $this->getUser();
            $project_member = $this->database->table("project_member");
            
            foreach($this->database->table("project")->where("id",$projectID) as $projekt){
                if($projekt->owner != $user->id){
                    $data = array(
                        "user_id"    => $user->id,
                        "project_id" => $projectID
                    );
                    $project_member->insert($data);

                    $this->flashMessage("Pridaný do projektu.");
                    $this->redirect('detail', $projectID);
                }else{
                    $this->flashMessage("Nemôžeš byť pridaný, lebo si zakladateľom tohto projektu!");
                }
            }
        }
    
	public function renderDefault()
	{
            $moje_projekty = $this->database->table("project")->where("owner",$this->user->id);
            $this->template->moje_projekty = $moje_projekty;

            $vsetky_projekty = $this->database->table("project");
            $this->template->vsetky_projekty = $vsetky_projekty;
	}
        
        public function createComponentNewProjectForm()
        {
            $form = new Form;
            $form->addText("name","Názov projektu")
                 ->setRequired("Zadaj názov projektu!")
                 ->getControlPrototype()->setClass("form-control");
            $form->addTextArea("description","Popis projektu",NULL,8)
                 ->setRequired("Napíš popis projektu!")
                 ->getControlPrototype()->setClass("form-control");
            $form->addSubmit('add', 'Vytvoriť')
                 ->getControlPrototype()->setClass("btn-u");
            $form->onSuccess[] = $this->newProjectSucceeded;
	    return $form;
        }
        
        public function newProjectSucceeded($form)
        {
	    $values = $form->getValues();
            $user = $this->getUser();
            if($user->isLoggedIn()){
                $data = array(
                    "name"        => $values->name,
                    "description" => $values->description,
                    "owner"       => $user->id
                );
                $this->database->table("project")->insert($data);
                $this->flashMessage("Nový projekt vytvorený!");
                $this->redirect('Projekt:default');
            }else{
                $this->flashMessage("Nikto nie je prihlásený.");
            }
        }

	public function renderDetail($id)
	{
		$this->id = $id;
		$this->template->project = $this->database->table("project")->where("id",$id)->fetch();
	}

	public function createComponentListTasks()
	{
		$konkretny_projekt = $this->database->table("project")->where("id",$this->id)->fetch(); 
		$form = new Form;
		$form->addText("name","Názov úlohy")
			->setRequired("Zadaj meno úlohy!")
			->getControlPrototype()->setClass("form-control");
		$form->addtextArea("description","Popis úlohy",NULL,8)
			->setRequired("Zadaj popis úlohy!")
			->getControlPrototype()->setClass("form-control");
		$items = array(
			$konkretny_projekt->owner => $konkretny_projekt->ref("users","owner")->name,
		);
		foreach($konkretny_projekt->related("project_member") as $user){
			$items[$user->user->id] = $user->user->name;
		}
		$form->addSelect("worker","Členovia",$items)
			->getControlPrototype()->setClass("form-control");
		$form->addSubmit("send","Pridať úlohu")
			->getControlPrototype()->setClass("btn-u");
		$form->onSuccess[] = $this->listTasksSucceeded;
		return $form;
	}

	public function listTasksSucceeded($form)
	{
		$values = $form->getValues();
		$values['owner'] = $this->user->id;
		$values['project_id'] = $this->id;
		$this->database->table("task")->insert($values); 
		$this->flashMessage("Úloha pridaná!");
		$this->redirect('detail',$this->id);
	}

	public function createComponentInviteUser()
	{
		$users = $this->database
			->table("users")
			->where("id NOT IN", 
				array_merge(
					array_keys(
						$this->database->table("project_member")
							->where("project_id", $this->id)
							->fetchPairs("user_id", "user_id")
					),
					array(
						$this->database->table("project")
							->where("id",$this->id)
							->fetch()
							->owner,
					)
				)
			)->fetchPairs("id", "name");
		$form = new Form;
		$form->addSelect("user_id",NULL,$users);
		$form->addSubmit("send","Pridať člena");
		$form->onSuccess[] = $this->inviteUser;
		return $form;
	}

	public function inviteUser($form)
	{
		if ($this->user->isLoggedIn()) {
			$owner = $this->database->table("project")
						->where("id",$this->id)
						->fetch()
						->owner;
			if ($this->user->id==$owner) {
				$values = $form->getValues();
				$values['project_id'] = $this->id;
				$this->database->table("project_member")->insert($values); 
				$this->flashMessage("Nový člen bol pridaný!", "info");
			} else {
				$this->flashMessage("Pridávať nových členov môže iba vlastník projektu!", "warning");	
			}
		} else {
			$this->flashMessage("Pre pridanie nového člena musíte byť prihlásený!", "warning");
		}
		$this->redirect('this');
	}
}