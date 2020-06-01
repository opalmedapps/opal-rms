New version of ORMS that has been remade from the old one.

Quick comments: 
	-When creating or deleting profiles in the WaitRoomMangement DB, be sure to use the stored procedures (SetupProfile/DeleteProfile respectively).
	-When adding a new column type, run the VerifyProfileColumns procedure right after.
	-When cloning/pulling for live use, make sure the WaitRoomManagement DB being used is the live one and not the dev
