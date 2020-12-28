New version of ORMS that has been remade from the old one.

Virtual Waiting Room notes:

Quick comments:
    -When creating or deleting profiles in the WaitRoomMangement DB, be sure to use the stored procedures (SetupProfile/DeleteProfile respectively).
    -When adding a new column type, run the VerifyProfileColumns procedure right after.
