USE [integral];
GO

IF OBJECT_ID(N'[dbo].[cmf_comerciales_calendario_festivos]', N'U') IS NOT NULL
BEGIN
    RAISERROR('La tabla [dbo].[cmf_comerciales_calendario_festivos] ya existe.', 16, 1);
    RETURN;
END;
GO

CREATE TABLE [dbo].[cmf_comerciales_calendario_festivos]
(
    [id] INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [fecha] DATE NOT NULL,
    [ambito] VARCHAR(20) NOT NULL,
    [cod_municipio_ine] VARCHAR(10) NULL,
    [provincia] VARCHAR(50) NULL,
    [poblacion] VARCHAR(100) NULL,
    [descripcion] VARCHAR(255) NULL,
    [origen] VARCHAR(100) NULL,
    [repetir_anualmente] BIT NOT NULL DEFAULT ((0))
);
GO

CREATE INDEX [idx_cal_festivos_fecha]
ON [dbo].[cmf_comerciales_calendario_festivos] ([fecha]);
GO

CREATE INDEX [idx_cal_festivos_ambito_geo]
ON [dbo].[cmf_comerciales_calendario_festivos] ([ambito], [provincia], [poblacion]);
GO

SELECT
    table_exists = OBJECT_ID(N'[dbo].[cmf_comerciales_calendario_festivos]', N'U'),
    idx_cal_festivos_fecha = INDEXPROPERTY(OBJECT_ID(N'[dbo].[cmf_comerciales_calendario_festivos]', N'U'), N'idx_cal_festivos_fecha', 'IndexId'),
    idx_cal_festivos_ambito_geo = INDEXPROPERTY(OBJECT_ID(N'[dbo].[cmf_comerciales_calendario_festivos]', N'U'), N'idx_cal_festivos_ambito_geo', 'IndexId');
GO
